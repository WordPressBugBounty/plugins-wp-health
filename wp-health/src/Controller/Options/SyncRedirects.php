<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Actions\BrokenLinkChecker\RedirectRouter;
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
            foreach (array_slice($params['redirects'], 0, RedirectRouter::MAX_REDIRECTS) as $redirect) {
                $sourcePattern = sanitize_text_field($redirect['sourcePattern'] ?? '');
                $matchType = sanitize_text_field($redirect['matchType'] ?? 'exact');
                $destinationUrl = esc_url_raw($redirect['destinationUrl'] ?? '');

                if (!$this->isValidRule($sourcePattern, $matchType, $destinationUrl)) {
                    continue;
                }

                $incoming[$sourcePattern] = [
                    'source_pattern' => $sourcePattern,
                    'destination_url' => $destinationUrl,
                    'redirect_type' => $this->normalizeRedirectType($redirect['httpCode'] ?? 301),
                    'match_type' => $matchType,
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

    protected function isValidRule($sourcePattern, $matchType, $destinationUrl)
    {
        if ($sourcePattern === ''
            || !preg_match('#^(https?://|/(?!/))#i', $destinationUrl)
            || parse_url($destinationUrl) === false
        ) {
            return false;
        }

        if ($matchType === 'regex'
            && (strlen($sourcePattern) > RedirectRouter::MAX_REGEX_PATTERN_LENGTH
                || @preg_match($sourcePattern, '') === false)
        ) {
            return false;
        }

        return !$this->redirectsToItself($sourcePattern, $matchType, $destinationUrl);
    }

    protected function redirectsToItself($sourcePattern, $matchType, $destinationUrl)
    {
        $destinationPath = $this->localPath($destinationUrl);

        if ($destinationPath === null || RedirectRouter::isProtectedPath($destinationPath)) {
            return false;
        }

        if ($matchType !== 'regex') {
            return rtrim($sourcePattern, '/') === $destinationPath;
        }

        return @preg_match($sourcePattern, $destinationPath) === 1;
    }

    /**
     * @return string|null The path the router would see, null for another host
     */
    protected function localPath($destinationUrl)
    {
        $parts = parse_url($destinationUrl);

        if (!is_array($parts)) {
            return null;
        }

        if (isset($parts['host'])) {
            $siteHost = parse_url(home_url(), PHP_URL_HOST);

            if (!is_string($siteHost) || strcasecmp($this->bareHost($parts['host']), $this->bareHost($siteHost)) !== 0) {
                return null;
            }
        }

        return rtrim(isset($parts['path']) ? $parts['path'] : '/', '/');
    }

    protected function bareHost($host)
    {
        return preg_replace('/^www\./i', '', $host);
    }

    protected function normalizeRedirectType($httpCode)
    {
        $httpCode = intval($httpCode);

        return in_array($httpCode, [301, 302, 307, 308], true) ? $httpCode : 301;
    }
}
