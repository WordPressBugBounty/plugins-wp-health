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

            $latestInstall = $this->getLatestPluginData();

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => [
                    'slug' => $latestInstall['slug'],
                    'plugin' => $latestInstall['main_file'],
                    'plugin_data' => $latestInstall['data']
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

    public function getLatestPluginData(): array
    {
        $plugins_dir = WP_PLUGIN_DIR;

        $dir_contents = scandir($plugins_dir);

        $plugin_dirs = array_filter($dir_contents, function ($dir) use ($plugins_dir) {
            return is_dir($plugins_dir . '/' . $dir) && $dir != '.' && $dir != '..';
        });

        usort($plugin_dirs, function ($a, $b) use ($plugins_dir) {
            return filemtime($plugins_dir . '/' . $b) - filemtime($plugins_dir . '/' . $a);
        });

        if (empty($plugin_dirs)) {
            return [
                'slug' => '',
                'main_file' => '',
                'data' => []
            ];
        }

        $latest_plugin_dir = $plugins_dir . '/' . $plugin_dirs[0];

        $latest_plugin_files = scandir($latest_plugin_dir);

        $main_plugin_file = '';

        $pluginData = [];
        foreach ($latest_plugin_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                $pluginData = get_plugin_data($latest_plugin_dir . '/' . $file);
                if (!empty($pluginData['Name'])) {
                    $main_plugin_file = $file;
                    break;
                }
            }
        }

        return [
            'slug' => $plugin_dirs[0],
            'main_file' => $plugin_dirs[0] . '/' . $main_plugin_file,
            'data' => $pluginData
        ];
    }
}
