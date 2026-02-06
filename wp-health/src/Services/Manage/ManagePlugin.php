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

    public function getVersionFromPluginFile($pluginFile)
    {
        try {
            if (!file_exists(WP_PLUGIN_DIR . '/' . $pluginFile)) {
                return false;
            }

            $content = file_get_contents(WP_PLUGIN_DIR . '/' . $pluginFile);
            if (!$content) {
                return false;
            }

            // Look for version in standard plugin header format
            if (preg_match('/Version:\s*(.+)$/mi', $content, $matches)) {
                return trim($matches[1]);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryPluginExist($plugin)
    {
        if (!$plugin) {
            return [
                'success' => false,
                'code' => 'missing_parameters',
            ];
        }

        $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($plugin);

        if (!file_exists($pluginDir) || !is_dir($pluginDir)) {
            return [
                'success' => false,
                'code' => 'plugin_directory_not_exist',
            ];
        }

        // Check if directory is not empty
        $files = scandir($pluginDir);
        if (count($files) <= 3) { // More than . and .. / Maybe .DS_Store or only 1 file is not enough
            return [
                'success' => false,
                'code' => 'plugin_directory_empty',
            ];
        }

        return [
            'success' => true,
        ];
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

        // As a precaution, we advise you to move the plugin in all cases, as plugins are sometimes deleted.
        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => dirname($plugin),
            'src' => WP_PLUGIN_DIR,
            'dir' => 'plugins'
        ]);

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
        @ob_start();

        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        // It's necessary because we update only one plugin even if it's a bulk update
        if (is_array($plugins)) {
            $plugin = $plugins[0];
        } else {
            $plugin = $plugins;
        }

        // As a precaution, we advise you to move the plugin in all cases, as plugins are sometimes deleted.
        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => dirname($plugin),
            'src' => WP_PLUGIN_DIR,
            'dir' => 'plugins'
        ]);

        if (isset($options['safe_update']) && $options['safe_update']) { // This condition is only required for safe update.
            if (!$result['success']) {
                return [
                    'status' => 'error',
                    'code' => $result['code'],
                    'message' => '',
                    'data' => ''
                ];
            }
        }

        $pluginUpdate = wp_umbrella_get_service('PluginUpdate');

        $pluginUpdate->ithemesCompatibility();
        $data = $pluginUpdate->bulkUpdate([$plugin], $options);

        $pluginUpdate->ithemesCompatibility();

        $isActive = wp_umbrella_get_service('PluginActivate')->isActive($plugin);

        if (!$isActive && $plugin !== 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginDeactivate')->deactivate($plugin);
        } elseif ($isActive || $plugin === 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginActivate')->activate($plugin);
        }

        @ob_end_clean();

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
