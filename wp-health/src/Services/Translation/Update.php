<?php
namespace WPUmbrella\Services\Translation;

use WPUmbrella\Core\Update\Translation\UpdaterSkin;
use Language_Pack_Upgrader;

if (!defined('ABSPATH')) {
    exit;
}

class Update
{
    const NAME_SERVICE = 'TranslationUpdate';

    const TYPES = ['core', 'plugin', 'theme'];

    const LANGUAGE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/D';

    /**
     * @param array $items Array of ['type' => string, 'slug' => string, 'language' => ?string], empty for every pending translation.
     *                     An item without language targets every language of its slug.
     */
    public function update($items = [])
    {
        try {
            wp_umbrella_debug_log('Translation update started (' . count($items) . ' item(s) requested)');

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/update.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            $types = $this->getTypes($items);

            $this->refreshTransients($types);

            $matched = $this->filterUpdates(wp_get_translation_updates(), $types, $items);
            $provider = wp_umbrella_get_service('LanguagesProvider');
            $updates = array_values(array_filter($matched, [$provider, 'hasPackage']));

            // bulk_upgrade() falls back to every pending pack when given an empty list.
            if (empty($updates)) {
                if (!empty($matched)) {
                    wp_umbrella_debug_log('Translation update: ' . count($matched) . ' pack(s) without package');
                    return $this->packagesUnavailable($matched);
                }

                wp_umbrella_debug_log('Translation update: nothing to update');
                return $this->noUpdateAvailable();
            }

            $skin = new UpdaterSkin();
            $upgrader = new Language_Pack_Upgrader($skin);

            $upgradeContext = wp_umbrella_get_service('UpgradeContext');
            $upgradeContext->begin();
            @ob_start();

            try {
                $result = $upgrader->bulk_upgrade($updates);
            } finally {
                @ob_end_clean();
                $upgradeContext->end();
            }

            if ($result === true) {
                return $this->noUpdateAvailable();
            }

            if ($result === false) {
                wp_umbrella_debug_log('Translation update: unable to connect to the filesystem');
                return [
                    'status' => 'error',
                    'code' => 'unable_connect_filesystem',
                ];
            }

            if (is_wp_error($result)) {
                wp_umbrella_debug_log('Translation update error: ' . $result->get_error_code() . ' - ' . $result->get_error_message());
                return [
                    'status' => 'error',
                    'code' => $result->get_error_code() ? $result->get_error_code() : 'unknown_error',
                    'message' => $result->get_error_message(),
                ];
            }

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => $this->buildReport($updates, $skin),
            ];
        } catch (\Throwable $e) {
            wp_umbrella_debug_log('Translation update exception: ' . $e->getMessage());

            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function noUpdateAvailable()
    {
        return [
            'status' => 'success',
            'code' => 'no_update_available',
            'data' => [
                'updated' => [],
                'failed' => [],
            ],
        ];
    }

    /**
     * The requested packs are pending but none can be installed, which is not
     * the same as having nothing left to update.
     */
    protected function packagesUnavailable($matched)
    {
        $provider = wp_umbrella_get_service('LanguagesProvider');

        $failed = [];
        foreach ($matched as $update) {
            $failed[] = array_merge($provider->format($update), [
                'code' => 'no_package',
                'message' => '',
            ]);
        }

        return [
            'status' => 'success',
            'code' => 'success',
            'data' => [
                'updated' => [],
                'failed' => $failed,
            ],
        ];
    }

    protected function getTypes($items)
    {
        if (empty($items)) {
            return self::TYPES;
        }

        return array_values(array_unique(array_column($items, 'type')));
    }

    protected function refreshTransients($types)
    {
        if (in_array('core', $types, true) && function_exists('wp_version_check')) {
            wp_version_check();
        }

        if (in_array('plugin', $types, true) && function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }

        if (in_array('theme', $types, true) && function_exists('wp_update_themes')) {
            wp_update_themes();
        }
    }

    protected function filterUpdates($updates, $types, $items)
    {
        $requested = [];
        foreach ($items as $item) {
            $language = isset($item['language']) ? $item['language'] : '*';
            $requested[$item['type'] . '|' . $item['slug'] . '|' . $language] = true;
        }

        $filtered = [];
        foreach ($updates as $update) {
            if (empty($update->type) || !in_array($update->type, $types, true)) {
                continue;
            }

            $slug = !empty($update->slug) ? $update->slug : 'default';
            $language = !empty($update->language) ? $update->language : '';
            $key = $update->type . '|' . $slug . '|';

            if (!empty($requested) && !isset($requested[$key . '*']) && !isset($requested[$key . $language])) {
                continue;
            }

            $filtered[] = $update;
        }

        return $filtered;
    }

    protected function buildReport($updates, UpdaterSkin $skin)
    {
        $provider = wp_umbrella_get_service('LanguagesProvider');

        $updated = [];
        $failed = [];

        foreach ($updates as $update) {
            $translation = $provider->format($update);
            $outcome = $skin->getOutcome($update);

            if ($outcome === true) {
                $updated[] = $translation;
                continue;
            }

            if ($outcome === null) {
                $outcome = [
                    'code' => 'unable_connect_filesystem',
                    'message' => '',
                ];
            }

            wp_umbrella_debug_log("Translation update failed for {$translation['type']} {$translation['slug']} {$translation['language']}: {$outcome['code']}");

            $failed[] = array_merge($translation, $outcome);
        }

        wp_umbrella_debug_log('Translation update completed: ' . count($updated) . ' updated, ' . count($failed) . ' failed');

        return [
            'updated' => $updated,
            'failed' => $failed,
        ];
    }
}
