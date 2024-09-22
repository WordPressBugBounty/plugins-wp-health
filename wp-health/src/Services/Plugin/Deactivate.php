<?php
namespace WPUmbrella\Services\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use Automatic_Upgrader_Skin;
use Exception;
use Plugin_Upgrader;
use WP_Error;

class Deactivate
{
    const NAME_SERVICE = 'PluginDeactivate';

    public function deactivate($plugin, $silent = true)
    {
        if (!function_exists('deactivate_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        $result = deactivate_plugins($plugin, $silent, wp_umbrella_get_service('PluginActivate')->isActiveForNetwork($plugin));

        if (is_wp_error($result)) {
            return [
                'status' => 'error',
                'code' => 'deactivation_fail',
                'data' => $result
            ];
        }

        return [
            'status' => 'success',
            'code' => 'success',
            'data' => $result
        ];
    }
}
