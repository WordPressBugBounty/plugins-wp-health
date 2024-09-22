<?php
namespace WPUmbrella\Services\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use Automatic_Upgrader_Skin;
use Exception;
use Plugin_Upgrader;
use WP_Error;

class Delete
{
    const NAME_SERVICE = 'PluginDelete';

    public function delete($plugin, $options = [])
    {
        $skipUninstallHook = isset($options['skip_uninstall_hook']) ? $options['skip_uninstall_hook'] : false;

        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';

        // Check that it's a valid plugin
        $valid = validate_plugin($plugin);
        if (is_wp_error($valid)) {
            return [
                'status' => 'error',
                'code' => 'plugin_not_valid',
            ];
        }

        if (wp_umbrella_get_service('PluginActivate')->isActive($plugin)) {
            if (is_multisite()) {
                return [
                    'status' => 'error',
                    'code' => 'plugin_active_on_subsite_network',
                ];
            }
			return [
				'status' => 'error',
				'code' => 'plugin_is_active',
			];
        }

        if (is_multisite() && is_plugin_active_for_network($plugin)) {
            return [
                'status' => 'error',
                'code' => 'plugin_active_on_network',
            ];
		}

        $result = delete_plugins([$plugin]);

        if (is_wp_error($result)) {
            return [
                'status' => 'error',
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ];
        }

        if (true === $result) {
            wp_clean_plugins_cache(false);
            return [
                'status' => 'success',
                'code' => 'success'
            ];
        }

        return [
            'status' => 'error',
            'code' => 'unknown_error',
        ];
    }
}
