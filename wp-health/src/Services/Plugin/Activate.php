<?php
namespace WPUmbrella\Services\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use Automatic_Upgrader_Skin;
use Exception;
use Plugin_Upgrader;
use WP_Error;

class Activate
{
    const NAME_SERVICE = 'PluginActivate';

    /**
     * @param string $plugin
     * @param bool $silent prevent activation hooks from running
     */
    public function activate($plugin, $silent = false)
    {
        if (!function_exists('activate_plugin')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        try {
            $result = activate_plugin($plugin, '', $this->isActiveForNetwork($plugin), $silent);

            if (is_wp_error($result)) {
                return [
                    'status' => 'error',
                    'code' => 'activation_fail',
                    'data' => $result
                ];
            }

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => $result
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 'activation_fail',
                'data' => $result
            ];
        }
    }

    public function isActive($plugin)
    {
        if (!function_exists('is_plugin_active') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($plugin);
    }

    public function isActiveForNetwork($plugin)
    {
        return is_plugin_active_for_network($plugin);
    }
}
