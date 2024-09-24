<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Helpers\GodTransient;

class UpgradeProcessComplete implements ExecuteHooks
{
    public function hooks()
    {
        add_action('upgrader_package_options', [$this, 'getOldVersion'], 10, 1);
        add_action('upgrader_process_complete', [$this, 'processComplete'], 10, 2);
        add_action('core_upgrade_preamble', [$this, 'getOldVersionWordPressCore']);
    }

    public function getOldVersionWordPressCore()
    {
        global $umbrellaPreUpdateData;

        $umbrellaPreUpdateData = [
            'old_version' => wp_umbrella_get_service('WordPressProvider')->getWordPressVersion(),
        ];
    }

    public function getOldVersion($options)
    {
        if (defined('WP_UMBRELLA_PROCESS_FROM_UMBRELLA') && WP_UMBRELLA_PROCESS_FROM_UMBRELLA) {
            return $options;
        }

        if (isset($options['hook_extra']['plugin'])) {
            wp_umbrella_get_service('TrackUpgradeProcess')->prepareDataBeforePluginUpdate([$options['hook_extra']['plugin']]);
        }

        if (isset($options['hook_extra']['theme'])) {
            wp_umbrella_get_service('TrackUpgradeProcess')->prepareDataBeforeThemeUpdate([$options['hook_extra']['theme']]);
        }

        return $options;
    }

    public function processComplete($upgraderObject, $options)
    {
        if (defined('WP_UMBRELLA_PROCESS_FROM_UMBRELLA') && WP_UMBRELLA_PROCESS_FROM_UMBRELLA) {
            return $options;
        }

        if ($options['action'] !== 'update') {
            return;
        }

        $json = [];

        switch ($options['type']) {
            case 'core':
                global $umbrellaPreUpdateData;

                $oldVersion = get_bloginfo('version');
                if (isset($umbrellaPreUpdateData['old_version'])) {
                    $oldVersion = $umbrellaPreUpdateData['old_version'];
                }

                $json = [
                    'type' => 'core',
                    'action' => 'update',
                    'values' => [
                        [
                            'old_version' => $oldVersion,
                            'new_version' => wp_umbrella_get_service('WordPressProvider')->getWordPressVersion(),
                        ]
                    ],
                ];

                break;
            case 'plugin':
                if (!isset($options['plugins'])) {
                    return $upgraderObject;
                }

                $data = wp_umbrella_get_service('TrackUpgradeProcess')->getDataAfterPluginUpdate($options['plugins']);

                if (empty($data)) {
                    return $upgraderObject;
                }

                foreach ($data as $plugin) {
                    $safeRemove = false;
                    if ($plugin['old_version'] === $plugin['new_version']) {
                        $safeRemove = true;
                    }

                    if (empty($plugin['new_version']) || empty($plugin['old_version'])) {
                        $safeRemove = true;
                    }

                    if ($safeRemove) {
                        $key = array_search($plugin, $data);
                        unset($data[$key]);
                    }
                }

                $json = [
                    'type' => 'plugin',
                    'action' => 'update',
                    'values' => $data,
                ];
                break;
            case 'theme':
                if (!isset($options['themes'])) {
                    return $upgraderObject;
                }

                $data = wp_umbrella_get_service('TrackUpgradeProcess')->getDataAfterThemeUpdate($options['themes']);

                if (empty($data)) {
                    return $upgraderObject;
                }

                foreach ($data as $theme) {
                    $safeRemove = false;
                    if ($theme['old_version'] === $theme['new_version']) {
                        $safeRemove = true;
                    }

                    if (empty($theme['new_version']) || empty($theme['old_version'])) {
                        $safeRemove = true;
                    }

                    if ($safeRemove) {
                        $key = array_search($theme, $data);
                        unset($data[$key]);
                    }
                }

                $json = [
                    'type' => 'theme',
                    'action' => 'update',
                    'values' => $data,
                ];
                break;
        }

        if (!empty($json) && !empty($json['values'])) {
            wp_umbrella_get_service('Processes')->addProcessTask($json);
        }

        return $upgraderObject;
    }
}
