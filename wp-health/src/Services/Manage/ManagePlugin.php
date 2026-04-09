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

        // Lock per-plugin to prevent concurrent update attempts on the same plugin
        $pluginSlug = dirname($plugin);
        $lockKey = 'wp_umbrella_update_lock_' . $pluginSlug;
        if (get_transient($lockKey)) {
            wp_umbrella_debug_log("ManagePlugin::update lock active for '{$pluginSlug}', returning update_in_progress");
            return [
                'status' => 'in_progress',
                'code' => 'update_in_progress',
                'message' => sprintf('An update is already in progress for plugin %s', $pluginSlug),
                'data' => ''
            ];
        }
        set_transient($lockKey, true, 300); // 5 minutes TTL — auto-expires if process dies

        // As a precaution, we advise you to move the plugin in all cases, as plugins are sometimes deleted.
        wp_umbrella_debug_log("ManagePlugin::update creating temp backup for '{$plugin}'");
        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => dirname($plugin),
            'src' => WP_PLUGIN_DIR,
            'dir' => 'plugins'
        ]);

        if (!$result['success'] && $requireBackup) {
            wp_umbrella_debug_log("ManagePlugin::update backup failed for '{$plugin}': " . ($result['code'] ?? 'unknown'));
            delete_transient($lockKey);
            return [
                'code' => 'temp_backup_required_failed',
                'message' => sprintf('Temporary backup failed for plugin %s: %s. Update aborted to ensure rollback safety.', $plugin, $result['code'] ?? 'unknown'),
                'data' => $result['message'] ?? ''
            ];
        }

        $isActive = wp_umbrella_get_service('PluginActivate')->isActive($plugin);

        $data = wp_umbrella_get_service('PluginUpdate')->update($plugin);

        if ($data['status'] === 'error' && $tryAjax) {
            wp_umbrella_debug_log("ManagePlugin::update plugin '{$plugin}' failed: " . ($data['code'] ?? 'unknown'));
            delete_transient($lockKey);
            return $data;
        }

        if (!$isActive && $plugin !== 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginDeactivate')->deactivate($plugin);
        } elseif ($isActive || $plugin === 'wp-health/wp-health.php') {
            wp_umbrella_get_service('PluginActivate')->activate($plugin);
        }

        wp_umbrella_debug_log("ManagePlugin::update plugin '{$plugin}' completed successfully");

        delete_transient($lockKey);

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
     *  - require_backup: bool
     *  - backup_done: bool
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

        // Lock per-plugin to prevent concurrent update attempts on the same plugin
        $lockKey = 'wp_umbrella_update_lock_' . $pluginSlug;
        $skipLock = isset($options['skip_lock']) && $options['skip_lock'];
        if (!$skipLock && get_transient($lockKey)) {
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate lock active for '{$pluginSlug}', returning update_in_progress");
            return [
                'status' => 'in_progress',
                'code' => 'update_in_progress',
                'message' => sprintf('An update is already in progress for plugin %s', $pluginSlug),
                'data' => ''
            ];
        }
        if (!$skipLock) {
            set_transient($lockKey, true, 300); // 5 minutes TTL — auto-expires if process dies
        }

        $maintenanceMode = wp_umbrella_get_service('MaintenanceMode');
        $stateManager = wp_umbrella_get_service('UpdateStateManager');
        $trace = wp_umbrella_get_service('RequestTrace');

        try {
            $trace->addTrace('bulk_update_started', ['plugin' => $plugin, 'require_backup' => !empty($options['require_backup'])]);
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate started for '{$plugin}' (require_backup: " . (!empty($options['require_backup']) ? 'true' : 'false') . ')');

            // Enable smart maintenance mode before backup (blocks visitors, allows WP Umbrella requests)
            wp_umbrella_debug_log('ManagePlugin::bulkUpdate enabling maintenance mode');
            $maintenanceMode->toggleMaintenanceMode(true);
            $trace->addTrace('maintenance_mode_enabled');

            // Backup is handled by:
            // 1. The worker's PrepareUpdateBackup handler via /prepare-update (separate HTTP request, umbrella >= 2.22.1)
            // 2. WordPress core's own move_to_temp_backup_dir (WP 6.3+, atomic rename)
            // We no longer create an inline backup here to avoid redundant disk I/O
            // that can cause timeouts on resource-constrained hosting.

            // Track the old version for rollback status reporting
            $oldVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);

            // Mark update as started — the worker reads this state before deciding on rollback
            $stateManager->setState($pluginSlug, $stateManager::STATUS_UPDATING, [
                'plugin' => $plugin,
                'old_version' => $oldVersion,
            ]);
            $trace->addTrace('state_set_updating', ['old_version' => $oldVersion]);

            // Hook into WP core's install_package result to detect rollback scheduling
            $this->registerWpCoreRollbackDetection($pluginSlug, $stateManager);

            $pluginUpdate = wp_umbrella_get_service('PluginUpdate');

            $pluginUpdate->ithemesCompatibility();

            wp_umbrella_debug_log("ManagePlugin::bulkUpdate calling PluginUpdate::bulkUpdate for '{$plugin}'");
            $trace->addTrace('bulk_upgrade_started');
            $data = $pluginUpdate->bulkUpdate([$plugin], $options);
            $trace->addTrace('bulk_upgrade_done', ['status' => $data['status'] ?? 'unknown']);

            $pluginUpdate->ithemesCompatibility();

            $isActive = wp_umbrella_get_service('PluginActivate')->isActive($plugin);

            if (!$isActive && $plugin !== 'wp-health/wp-health.php') {
                wp_umbrella_get_service('PluginDeactivate')->deactivate($plugin);
            } elseif ($isActive || $plugin === 'wp-health/wp-health.php') {
                wp_umbrella_get_service('PluginActivate')->activate($plugin);
            }
            $trace->addTrace('activation_state_restored', ['is_active' => $isActive]);

            // Disable maintenance mode after update completes
            $maintenanceMode->toggleMaintenanceMode(false);
            $trace->addTrace('maintenance_mode_disabled');

            // Mark final state based on update result
            $hasError = isset($data['status']) && $data['status'] === 'error';
            if ($hasError) {
                $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_FAILED, [
                    'error_code' => $data['code'] ?? 'unknown',
                ]);
                $trace->addTrace('state_set_failed', ['error_code' => $data['code'] ?? 'unknown']);
            } else {
                $newVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_COMPLETED, [
                    'new_version' => $newVersion,
                ]);
                $trace->addTrace('state_set_completed', ['new_version' => $newVersion]);
            }

            wp_umbrella_debug_log("ManagePlugin::bulkUpdate completed for '{$plugin}': " . ($data['status'] ?? 'unknown'));

            @ob_end_clean();

            if (!$skipLock) {
                delete_transient($lockKey);
            }

            return $data;
        } catch (\Throwable $e) {
            if (!$skipLock) {
                delete_transient($lockKey);
            }
            $trace->addTrace('exception', ['message' => $e->getMessage()]);
            wp_umbrella_debug_log("ManagePlugin::bulkUpdate exception for '{$plugin}': " . $e->getMessage());

            // Check if the update actually succeeded despite the exception
            // (e.g. WooCommerce reactivation crashes after a successful upgrade)
            $currentVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
            $versionActuallyChanged = isset($oldVersion) && !empty($currentVersion) && $currentVersion !== $oldVersion;

            if ($versionActuallyChanged) {
                $trace->addTrace('exception_but_version_changed', ['from' => $oldVersion, 'to' => $currentVersion]);
                wp_umbrella_debug_log("ManagePlugin::bulkUpdate exception for '{$plugin}' but version changed ({$oldVersion} → {$currentVersion}), treating as success");

                $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_COMPLETED, [
                    'new_version' => $currentVersion,
                    'exception' => $e->getMessage(),
                ]);

                $maintenanceMode->toggleMaintenanceMode(false);

                @ob_end_clean();

                return [
                    'status' => 'success',
                    'code' => 'success',
                    'data' => [
                        $plugin => 'success',
                        $plugin . '_old_version' => $oldVersion,
                        $plugin . '_new_version' => $currentVersion,
                    ],
                ];
            }

            $requireBackup = isset($options['require_backup']) && $options['require_backup'];

            if ($requireBackup) {
                // Safe update — attempt rollback via PluginUpdate
                $rollback = wp_umbrella_get_service('PluginUpdate')->rollbackIfCorrupted($pluginSlug);
            } else {
                // Quick update — no auto-rollback
                $rollback = ['status' => 'not_needed', 'reason' => null];
                wp_umbrella_debug_log("ManagePlugin::bulkUpdate quick update exception, skipping rollback for '{$pluginSlug}'");
            }

            // Mark state based on rollback outcome
            $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_FAILED, [
                'error_code' => 'exception',
                'error_message' => $e->getMessage(),
                'rollback_status' => $rollback['status'],
            ]);

            $maintenanceMode->toggleMaintenanceMode(false);

            @ob_end_clean();

            if ($rollback['status'] === 'rollback_performed') {
                return [
                    'status' => 'error',
                    'code' => 'rollback_performed',
                    'message' => $e->getMessage(),
                    'rollback_performed' => true,
                    'rollback_reason' => $rollback['reason'],
                    'restored_version' => isset($oldVersion) ? $oldVersion : null,
                    'data' => ''
                ];
            }

            if ($rollback['status'] === 'rollback_failed') {
                return [
                    'status' => 'error',
                    'code' => 'rollback_failed',
                    'message' => $e->getMessage(),
                    'rollback_performed' => false,
                    'rollback_reason' => $rollback['reason'],
                    'data' => ''
                ];
            }

            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
                'data' => ''
            ];
        }
    }

    /**
     * Register hooks to detect WP core's own rollback mechanism.
     *
     * - `upgrader_install_package_result`: fires after install_package() — if the result is
     *   a WP_Error with a temp_backup, WP core will schedule a rollback on shutdown.
     * - `shutdown` at priority 50: fires AFTER WP core's restore (priority 10) but BEFORE
     *   WP core's delete (priority 100) — allows us to check if the rollback succeeded.
     */
    protected function registerWpCoreRollbackDetection($pluginSlug, $stateManager)
    {
        // Detect when WP core schedules a rollback (install_package failed with temp_backup)
        add_filter('upgrader_install_package_result', function ($result, $hookExtra) use ($pluginSlug, $stateManager) {
            if (!is_wp_error($result)) {
                return $result;
            }

            if (empty($hookExtra['temp_backup'])) {
                return $result;
            }

            $backupSlug = isset($hookExtra['temp_backup']['slug']) ? $hookExtra['temp_backup']['slug'] : null;
            if ($backupSlug !== $pluginSlug) {
                return $result;
            }

            wp_umbrella_debug_log("ManagePlugin: WP core rollback scheduled for '{$pluginSlug}'");
            $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_WP_CORE_ROLLBACK_SCHEDULED);

            // After WP core's restore_temp_backup (shutdown priority 10), check the result
            add_action('shutdown', function () use ($pluginSlug, $stateManager) {
                $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $pluginSlug;
                $wpCoreBackupDir = WP_CONTENT_DIR . '/upgrade-temp-backup/plugins/' . $pluginSlug;

                if (is_dir($pluginDir) && !is_dir($wpCoreBackupDir)) {
                    // Plugin directory restored and backup consumed → WP core rollback succeeded
                    wp_umbrella_debug_log("ManagePlugin: WP core rollback succeeded for '{$pluginSlug}'");
                    $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_WP_CORE_ROLLBACK_DONE);
                } else {
                    // Plugin still missing or backup still present → WP core rollback failed
                    wp_umbrella_debug_log("ManagePlugin: WP core rollback failed for '{$pluginSlug}' (plugin_dir: " . (is_dir($pluginDir) ? 'exists' : 'missing') . ', wp_backup: ' . (is_dir($wpCoreBackupDir) ? 'exists' : 'missing') . ')');
                    $stateManager->updateStatus($pluginSlug, $stateManager::STATUS_WP_CORE_ROLLBACK_FAILED);
                }
            }, 50);

            return $result;
        }, 999, 2);
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
