<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\BrokenLinkChecker\RedirectTableManager;

class SyncRedirects extends AbstractController
{
    public function executeGet($params)
    {
        return $this->sync($params);
    }

    public function executePost($params)
    {
        return $this->sync($params);
    }

    /**
     * Diff-based sync: insert new, update changed, delete removed
     */
    protected function sync($params)
    {
        global $wpdb;
        $tableName = RedirectTableManager::getTableName();

        $incoming = [];
        if (!empty($params['redirects']) && is_array($params['redirects'])) {
            foreach ($params['redirects'] as $redirect) {
                $sourcePattern = sanitize_text_field($redirect['sourcePattern'] ?? '');
                if (empty($sourcePattern)) {
                    continue;
                }

                $incoming[$sourcePattern] = [
                    'source_pattern' => $sourcePattern,
                    'destination_url' => esc_url_raw($redirect['destinationUrl'] ?? ''),
                    'redirect_type' => intval($redirect['httpCode'] ?? 301),
                    'match_type' => sanitize_text_field($redirect['matchType'] ?? 'exact'),
                ];
            }
        }

        // Fetch existing rows keyed by source_pattern
        $existing = [];
        $rows = $wpdb->get_results("SELECT id, source_pattern, destination_url, redirect_type, match_type FROM {$tableName}", ARRAY_A);
        foreach ($rows ?: [] as $row) {
            $existing[$row['source_pattern']] = $row;
        }

        // Delete rules no longer present
        $toDelete = array_diff(array_keys($existing), array_keys($incoming));
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '%s'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tableName} WHERE source_pattern IN ({$placeholders})",
                ...$toDelete
            ));
        }

        // Insert or update
        foreach ($incoming as $sourcePattern => $data) {
            if (!isset($existing[$sourcePattern])) {
                $wpdb->insert($tableName, $data);
            } else {
                $existingRow = $existing[$sourcePattern];
                $changed = $existingRow['destination_url'] !== $data['destination_url']
                    || intval($existingRow['redirect_type']) !== $data['redirect_type']
                    || $existingRow['match_type'] !== $data['match_type'];

                if ($changed) {
                    $wpdb->update(
                        $tableName,
                        $data,
                        ['id' => $existingRow['id']]
                    );
                }
            }
        }

        return $this->returnResponse(['success' => true]);
    }
}
