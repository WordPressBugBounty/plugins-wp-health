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
    }

    public function getOldVersion($options)
    {
        if (defined('WP_UMBRELLA_PROCESS_FROM_UMBRELLA') && WP_UMBRELLA_PROCESS_FROM_UMBRELLA) {
            return $options;
        }

        if (isset($options['hook_extra']['plugin'])) {
            wp_umbrella_get_service('TrackUpgradeProcess')->prepareDataBeforePluginUpdate([$options['hook_extra']['plugin']]);
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
                return $upgraderObject; // Not implemented yet
                break;
            case 'plugin':
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
                return $upgraderObject; // Not implemented yet
                break;
        }

        if (!empty($json) && !empty($json['values'])) {
            wp_umbrella_get_service('Processes')->addProcessTask($json);
        }

        return $upgraderObject;
    }
}
