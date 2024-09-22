<?php
namespace WPUmbrella\Services\Manage;

if (!defined('ABSPATH')) {
    exit;
}

use Automatic_Upgrader_Skin;
use Exception;
use Plugin_Upgrader;
use WP_Error;
use function wp_umbrella_get_service;

class ManagePlugin
{
    public function clearUpdates()
    {
        $key = 'update_plugins';

        $response = get_site_transient($key);

        set_transient($key, $response);
        // Need to trigger pre_site_transient
        set_site_transient($key, $response);
    }

    public function install($pluginUri, $overwrite = true)
    {
        $response = wp_umbrella_get_service('PluginInstall')->install($pluginUri);
        return $response;
    }

    /**
     *
     * @param string $plugin
     * @return array
     */
    public function update($plugin, $options = [])
    {
        $tryAjax = isset($options['try_ajax']) ? $options['try_ajax'] : true;

        $pluginItem = wp_umbrella_get_service('PluginsProvider')->getPluginByFile($plugin, [
            'clear_updates' => false,
        ]);

        if (!$pluginItem) {
            return [
                'code' => 'plugin_not_exist',
                'message' => sprintf(__('Plugin %s not exist', 'wp-umbrella'), $plugin)
            ];
        }

        $isActive = wp_umbrella_get_service('PluginActivate')->isActive($plugin);

        $data = wp_umbrella_get_service('PluginUpdate')->update($plugin);

        if ($data['status'] === 'error' && $tryAjax) {
            return $data;
        }

        if (!$isActive && $plugin !== 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginDeactivate')->deactivate($plugin);
        } elseif ($isActive || $plugin === 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginActivate')->activate($plugin);
        }

        return [
            'status' => 'success',
            'code' => 'success',
            'message' => sprintf('The %s plugin successfully updated', $plugin),
            'data' => isset($data['data']) ?? false
        ];
    }

    /**
     *
     * @param array $plugins
     * @param array $options
     *  - only_ajax: bool
     *  - safe_update: bool
     * @return array
     */
    public function bulkUpdate($plugins, $options = [])
    {
        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        if (isset($options['safe_update']) && $options['safe_update']) {
            // It's necessary because we update only one plugin even if it's a bulk update
            if (is_array($plugins)) {
                $plugin = $plugins[0];
            } else {
                $plugin = $plugins;
            }

            $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
                'slug' => dirname($plugin),
                'src' => WP_PLUGIN_DIR,
                'dir' => 'plugins'
            ]);

            if (!$result['success']) {
                return [
                    'status' => 'error',
                    'code' => $result['code'],
                    'message' => '',
                    'data' => ''
                ];
            }
        }

        @ob_start();
        $pluginUpdate = wp_umbrella_get_service('PluginUpdate');

        $pluginUpdate->ithemesCompatibility();
        $data = $pluginUpdate->bulkUpdate($plugins, $options);

        $pluginUpdate->ithemesCompatibility();
        @flush();
        @ob_clean();
        @ob_end_clean();

        if (isset($options['safe_update']) && $options['safe_update']) {
            $result = wp_umbrella_get_service('UpgraderTempBackup')->deleteTempBackup([
                'slug' => dirname($plugin),
                'dir' => 'plugins'
            ]);
        }

        return $data;
    }

    /**
     *
     * @param string $pluginFile
     * @param array $options [version, is_active]
     * @return array
     */
    public function rollback($pluginFile, $options = [])
    {
        if (!isset($options['version'])) {
            return [
                'status' => 'error',
                'code' => 'rollback_missing_version',
                'message' => 'Missing version parameter',
                'data' => null
            ];
        }

        $isActive = false;
        if (!isset($options['is_active'])) {
            $isActive = wp_umbrella_get_service('PluginActivate')->isActive($pluginFile);
        } else {
            $isActive = $options['is_active'];
        }

        $plugin = wp_umbrella_get_service('PluginsProvider')->getPlugin($pluginFile);

        if (!$plugin) {
            return [
                'status' => 'error',
                'code' => 'rollback_plugin_not_exist',
                'message' => 'Plugin not exist',
                'data' => null
            ];
        }

        $data = wp_umbrella_get_service('PluginRollback')->rollback([
            'name' => $plugin->name,
            'slug' => $plugin->slug,
            'version' => $options['version'],
            'plugin_file' => $pluginFile
        ]);

        if ($data !== true) {
            return [
                'status' => 'error',
                'code' => 'rollback_version_not_exist',
                'message' => sprintf('Version %s not exist', $options['version']),
                'data' => null
            ];
        }

        if ($isActive) {
            wp_umbrella_get_service('PluginActivate')->activate($pluginFile);
        } else {
            wp_umbrella_get_service('PluginDeactivate')->deactivate($pluginFile);
        }

        return [
            'status' => 'success',
            'code' => 'success',
            'message' => 'Plugin rollback successful',
            'data' => null
        ];
    }

    public function delete($plugin, $options = [])
    {
        $pluginItem = wp_umbrella_get_service('PluginsProvider')->getPlugin($plugin);

        if (!$pluginItem) {
            return [
                'code' => 'plugin_not_exist',
                'message' => sprintf(__('Plugin %s not exist', 'wp-umbrella'), $plugin)
            ];
        }

        return wp_umbrella_get_service('PluginDelete')->delete($plugin, $options);
    }
}
