<?php
namespace WPUmbrella\Services\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;
use WPUmbrella\Services\Manage\ManagePlugin;

class Install
{
    const NAME_SERVICE = 'PluginInstall';

    public function install($urlToInstall, $overwrite = true): array
    {
        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        try {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);

            add_filter('upgrader_package_options', function ($options) use ($overwrite) {
                $options['clear_destination'] = $overwrite;
                return $options;
            });

            $result = $upgrader->install($urlToInstall);

            if ($result !== true) {
                return [
                    'status' => 'error',
                    'code' => 'install_fail_may_not_exist',
                    'message' => '',
                    'data' => [
                        'uri' => $urlToInstall
                    ]
                ];
            }

            if (is_wp_error($result)) {
                /** @var WP_Error $result */
                return [
                    'status' => 'error',
                    'code' => 'install_fail',
                    'message' => is_wp_error($result) ? $result->get_error_message() : '',
                    'data' => $result
                ];
            }

            // WP populates $upgrader->plugin_info() with the main file of the package
            // it just extracted. The previous scandir + filemtime heuristic returned
            // whichever plugin dir on disk had the latest mtime, which races with any
            // other plugin writing to its own folder during the install.
            $mainFile = $upgrader->plugin_info();

            if (empty($mainFile)) {
                return [
                    'status' => 'error',
                    'code' => 'install_fail_unknown_main_file',
                    'message' => 'Install reported success but plugin main file could not be resolved',
                    'data' => [
                        'uri' => $urlToInstall
                    ]
                ];
            }

            $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $mainFile);

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => [
                    'slug' => dirname($mainFile),
                    'plugin' => $mainFile,
                    'plugin_data' => $pluginData
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'code' => 'install_fail',
                'data' => $e->getMessage()
            ];
        }
    }
}
