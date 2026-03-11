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

        wp_umbrella_debug_log("ManagePlugin::update started for '{$plugin}'");

        $pluginItem = wp_umbrella_get_service('PluginsProvider')->getPluginByFile($plugin, [
            'clear_updates' => false,
        ]);

        if (!$pluginItem) {
            wp_umbrella_debug_log("ManagePlugin::update plugin '{$plugin}' not found");
            return [
                'code' => 'plugin_not_exist',
                'message' => sprintf(__('Plugin %s not exist', 'wp-umbrella'), $plugin)
            ];
        }

        // As a precaution, we advise you to move the plugin in all cases, as plugins are sometimes deleted.
        wp_umbrella_debug_log("ManagePlugin::update creating temp backup for '{$plugin}'");
        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => dirname($plugin),
            'src' => WP_PLUGIN_DIR,
            'dir' => 'plugins'
        ]);

        $isActive = wp_umbrella_get_service('PluginActivate')->isActive($plugin);

        $data = wp_umbrella_get_service('PluginUpdate')->update($plugin);

        if ($data['status'] === 'error' && $tryAjax) {
            wp_umbrella_debug_log("ManagePlugin::update plugin '{$plugin}' failed: " . ($data['code'] ?? 'unknown'));
            return $data;
        }

        if (!$isActive && $plugin !== 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginDeactivate')->deactivate($plugin);
        } elseif ($isActive || $plugin === 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginActivate')->activate($plugin);
        }

        wp_umbrella_debug_log("ManagePlugin::update plugin '{$plugin}' completed successfully");

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

        $pluginSlug = dirname($plugin);

        $maintenanceMode = wp_umbrella_get_service('MaintenanceMode');

        try {
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate started for '{$plugin}' (safe_update: " . (!empty($options['safe_update']) ? 'true' : 'false') . ")");

            // Enable smart maintenance mode before backup (blocks visitors, allows WP Umbrella requests)
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate enabling maintenance mode");
            $maintenanceMode->toggleMaintenanceMode(true);

            // As a precaution, we advise you to move the plugin in all cases, as plugins are sometimes deleted.
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate creating temp backup for '{$pluginSlug}'");
            $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
                'slug' => $pluginSlug,
                'src' => WP_PLUGIN_DIR,
                'dir' => 'plugins'
            ]);
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate temp backup result: " . ($result['success'] ? 'success' : ($result['code'] ?? 'failed')));

            $requireBackup = isset($options['require_backup']) && $options['require_backup'];

            if (!$result['success'] && ($requireBackup || (isset($options['safe_update']) && $options['safe_update']))) {
                wp_umbrella_debug_log("ManagePlugin::bulkUpdate backup failed for '{$pluginSlug}': " . ($result['code'] ?? 'unknown'));
                $maintenanceMode->toggleMaintenanceMode(false);

                return [
                    'status' => 'error',
                    'code' => 'temp_backup_required_failed',
                    'message' => sprintf('Temporary backup failed for plugin %s: %s. Update aborted to ensure rollback safety.', $pluginSlug, $result['code'] ?? 'unknown'),
                    'data' => ''
                ];
            }

            // Register shutdown handler to restore plugin if PHP crashes mid-update
            // Same pattern as WordPress core WP_Upgrader::run() (shutdown actions are immune to PHP timeouts)
            register_shutdown_function(function () use ($pluginSlug) {
                $error = error_get_last();
                if ($error === null) {
                    return;
                }

                if (!in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                    return;
                }

                $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $pluginSlug;
                if (file_exists($pluginDir) && is_dir($pluginDir)) {
                    return;
                }

                try {
                    wp_umbrella_debug_log("Shutdown handler: fatal error detected, plugin '{$pluginSlug}' directory missing. Attempting rollback. Error: {$error['message']}");
                    $rollbackResult = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                        'dir' => 'plugins',
                        'slug' => $pluginSlug,
                    ]);
                    $success = isset($rollbackResult['success']) && $rollbackResult['success'];
                    wp_umbrella_debug_log('Shutdown handler: rollback ' . ($success ? 'succeeded' : 'failed') . " for plugin '{$pluginSlug}'");
                } catch (\Throwable $e) {
                    // Best effort - the worker-side CheckPluginDirectoryExist remains the final safety net
                }

                // Cleanup maintenance mode to prevent site staying locked after crash
                $maintenanceFile = ABSPATH . '.maintenance';
                if (file_exists($maintenanceFile)) {
                    @unlink($maintenanceFile);
                }
            });

            $pluginUpdate = wp_umbrella_get_service('PluginUpdate');

            $pluginUpdate->ithemesCompatibility();

            wp_umbrella_debug_log("ManagePlugin::bulkUpdate calling PluginUpdate::bulkUpdate for '{$plugin}'");
            $data = $pluginUpdate->bulkUpdate([$plugin], $options);

            $pluginUpdate->ithemesCompatibility();

            // Auto-rollback if plugin directory is missing after update
            $this->attemptRollbackIfMissing($plugin);

            $isActive = wp_umbrella_get_service('PluginActivate')->isActive($plugin);

            if (!$isActive && $plugin !== 'wp-health/wp-health.php') {
                wp_umbrella_get_service('PluginDeactivate')->deactivate($plugin);
            } elseif ($isActive || $plugin === 'wp-health/wp-health.php') {
                wp_umbrella_get_service('PluginActivate')->activate($plugin);
            }

            // Disable maintenance mode after update completes
            $maintenanceMode->toggleMaintenanceMode(false);

            wp_umbrella_debug_log("ManagePlugin::bulkUpdate completed for '{$plugin}': " . ($data['status'] ?? 'unknown'));

            @ob_end_clean();

            return $data;
        } catch (\Throwable $e) {
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate exception for '{$plugin}': " . $e->getMessage());
            $this->attemptRollbackIfMissing($plugin);
            $maintenanceMode->toggleMaintenanceMode(false);

            @ob_end_clean();

            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
                'data' => ''
            ];
        }
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

    private function attemptRollbackIfMissing($plugin)
    {
        $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($plugin);
        if (file_exists($pluginDir) && is_dir($pluginDir)) {
            return;
        }

        try {
            wp_umbrella_debug_log("attemptRollbackIfMissing: plugin '{$plugin}' directory missing. Attempting rollback from backup.");
            $rollbackResult = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'plugins',
                'slug' => dirname($plugin),
            ]);
            $success = isset($rollbackResult['success']) && $rollbackResult['success'];
            wp_umbrella_debug_log('attemptRollbackIfMissing: rollback ' . ($success ? 'succeeded' : 'failed') . " for plugin '{$plugin}'");
        } catch (\Throwable $e) {
            // Best effort - the worker-side CheckPluginDirectoryExist remains the final safety net
        }
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
