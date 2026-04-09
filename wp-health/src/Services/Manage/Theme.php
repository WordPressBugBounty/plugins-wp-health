<?php
namespace WPUmbrella\Services\Manage;

use Automatic_Upgrader_Skin;
use Exception;
use Theme_Upgrader;
use WP_Error;
use WP_Ajax_Upgrader_Skin;

class Theme
{
    const NAME_SERVICE = 'ManageTheme';

    public function getVersionFromThemeFile($theme)
    {
        try {
            if (!file_exists(get_theme_root($theme))) {
                return false;
            }

            $filePath = get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR . 'style.css';
            if (!file_exists($filePath)) {
                return false;
            }

            $content = file_get_contents(get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR . 'style.css');
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

    public function themeDirectoryExist($theme)
    {
        if (!$theme) {
            return [
                'success' => false,
                'code' => 'missing_parameters',
            ];
        }

        $themeDir = get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme;

        if (!file_exists($themeDir) || !is_dir($themeDir)) {
            return [
                'success' => false,
                'code' => 'theme_directory_not_exist',
            ];
        }

        // Check if directory is not empty
        $files = scandir($themeDir);
        if (count($files) <= 3) { // More than . and .. / Maybe .DS_Store or only 1 file is not enough
            return [
                'success' => false,
                'code' => 'theme_directory_empty',
            ];
        }

        return [
            'success' => true,
        ];
    }

    public function generateSafeUpdateBackupTheme($theme)
    {
        // As a precaution, we advise you to move the theme in all cases, as themes are sometimes deleted.
        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => $theme,
            'src' => get_theme_root($theme),
            'dir' => 'themes'
        ]);

        wp_umbrella_debug_log("Theme '{$theme}' temp backup result: " . ($result['success'] ? 'success' : ($result['code'] ?? 'failed')));

        return $result;
    }

    public function getError($error_object)
    {
        if (!is_wp_error($error_object)) {
            return $error_object != '' ? $error_object : '';
        } else {
            $errors = [];
            if (!empty($error_object->error_data)) {
                foreach ($error_object->error_data as $error_key => $error_string) {
                    $errors[] = str_replace('_', ' ', ucfirst($error_key)) . ': ' . $error_string;
                }
            } elseif (!empty($error_object->errors)) {
                foreach ($error_object->errors as $error_key => $err) {
                    $errors[] = 'Error: ' . str_replace('_', ' ', strtolower($error_key));
                }
            }

            return implode('<br />', $errors);
        }
    }

    public function update($theme, $options = [])
    {
        $maintenanceMode = wp_umbrella_get_service('MaintenanceMode');
        $stateManager = wp_umbrella_get_service('UpdateStateManager');
        $trace = wp_umbrella_get_service('RequestTrace');

        try {
            $trace->addTrace('theme_update_started', ['theme' => $theme]);
            wp_umbrella_debug_log("Theme update started for '{$theme}'");

            // Lock per-theme to prevent concurrent update attempts on the same theme
            $lockKey = 'wp_umbrella_update_lock_' . $theme;
            if (get_transient($lockKey)) {
                wp_umbrella_debug_log("Theme update lock active for '{$theme}', returning update_in_progress");
                $trace->addTrace('update_in_progress_lock');
                return [
                    'status' => 'in_progress',
                    'code' => 'update_in_progress',
                    'message' => sprintf('An update is already in progress for theme %s', $theme),
                    'data' => ''
                ];
            }
            set_transient($lockKey, true, 300); // 5 minutes TTL

            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            // Store old version for verification
            $oldVersion = $this->getVersionFromThemeFile($theme);
            wp_umbrella_debug_log("Theme '{$theme}' current version: " . ($oldVersion ?: 'unknown'));

            // Enable smart maintenance mode before backup (blocks visitors, allows WP Umbrella requests)
            wp_umbrella_debug_log("Theme '{$theme}' enabling maintenance mode");
            $maintenanceMode->toggleMaintenanceMode(true);
            $trace->addTrace('maintenance_mode_enabled');

            // Skip backup if already done by /prepare-update endpoint (separate HTTP request)
            $backupDone = isset($options['backup_done']) && $options['backup_done'];

            if ($backupDone) {
                wp_umbrella_debug_log("Theme '{$theme}' backup already done by /prepare-update");
            } else {
                wp_umbrella_debug_log("Theme '{$theme}' creating temp backup");
                $backupResult = $this->generateSafeUpdateBackupTheme($theme);

                $requireBackup = isset($options['require_backup']) ? (bool) $options['require_backup'] : true;
                if ($requireBackup && !$backupResult['success']) {
                    wp_umbrella_debug_log("Theme '{$theme}' backup failed: " . ($backupResult['code'] ?? 'unknown'));
                    $trace->addTrace('backup_failed', ['code' => $backupResult['code'] ?? 'unknown']);
                    delete_transient($lockKey);
                    $maintenanceMode->toggleMaintenanceMode(false);

                    return [
                        'status' => 'error',
                        'code' => 'temp_backup_required_failed',
                        'message' => sprintf('Temporary backup failed for theme %s: %s. Update aborted to ensure rollback safety.', $theme, $backupResult['code'] ?? 'unknown'),
                        'data' => ''
                    ];
                }
            }

            // Mark update as started
            $stateManager->setState($theme, $stateManager::STATUS_UPDATING, [
                'old_version' => $oldVersion,
            ], 'theme');
            $trace->addTrace('state_set_updating', ['old_version' => $oldVersion]);

            // Hook into WP core's install_package result to detect rollback scheduling
            $this->registerWpCoreRollbackDetection($theme, $stateManager);

            $themeSlug = $theme;
            $themeRoot = get_theme_root($theme);

            @ob_start();

            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);

            wp_umbrella_debug_log("Theme '{$theme}' running bulk_upgrade...");
            $trace->addTrace('bulk_upgrade_call');
            $response = $upgrader->bulk_upgrade([$theme]);
            $trace->addTrace('bulk_upgrade_returned', ['empty' => empty($response)]);

            @flush();
            @ob_clean();
            @ob_end_clean();

            $result = $this->processUpgradeResult($theme, $response, $skin, $oldVersion);
            $trace->addTrace('upgrade_result', ['status' => $result['status'], 'code' => $result['code'] ?? 'unknown']);

            $requireBackup = isset($options['require_backup']) ? (bool) $options['require_backup'] : true;

            if ($result['status'] === 'error') {
                wp_umbrella_debug_log("Theme '{$theme}' update failed: " . ($result['code'] ?? 'unknown') . ' - ' . ($result['message'] ?? ''));
                $trace->addTrace('state_set_failed', ['error_code' => $result['code'] ?? 'unknown']);

                $stateManager->updateStatus($theme, $stateManager::STATUS_FAILED, [
                    'error_code' => $result['code'] ?? 'unknown',
                ], 'theme');

                if ($requireBackup) {
                    // Safe update — rollback to restore a safe state
                    $trace->addTrace('rollback_started');
                    $rollback = $this->performThemeRollback($theme, 'update failed with code: ' . ($result['code'] ?? 'unknown'));
                    $trace->addTrace('rollback_done', ['status' => $rollback['status']]);
                    $result['code'] = $rollback['status'];
                    $result['rollback_performed'] = $rollback['status'] === 'rollback_performed';
                    $result['rollback_reason'] = $rollback['reason'];
                    $result['restored_version'] = $oldVersion;
                } else {
                    // Quick update — no auto-rollback
                    wp_umbrella_debug_log("Theme '{$theme}' quick update failed, skipping rollback");
                }
            } else if ($requireBackup) {
                // Safe update — verify integrity (directory + style.css)
                $trace->addTrace('integrity_check_started');
                $rollback = $this->rollbackIfCorrupted($theme);

                if ($rollback['status'] !== 'not_needed') {
                    wp_umbrella_debug_log("Theme '{$theme}' update succeeded but integrity check failed");
                    $trace->addTrace('integrity_check_failed', ['rollback_status' => $rollback['status']]);

                    $stateManager->updateStatus($theme, $stateManager::STATUS_FAILED, [
                        'error_code' => 'integrity_check_failed',
                    ], 'theme');

                    $result = [
                        'status' => 'error',
                        'code' => $rollback['status'],
                        'message' => 'Theme update succeeded but integrity check failed',
                        'rollback_performed' => $rollback['status'] === 'rollback_performed',
                        'rollback_reason' => $rollback['reason'],
                        'restored_version' => $oldVersion,
                        'data' => ''
                    ];
                } else {
                    wp_umbrella_debug_log("Theme '{$theme}' successfully updated");
                    $newVersion = $this->getVersionFromThemeFile($theme);
                    $trace->addTrace('state_set_completed', ['new_version' => $newVersion]);
                    $stateManager->updateStatus($theme, $stateManager::STATUS_COMPLETED, [
                        'new_version' => $newVersion,
                    ], 'theme');
                }
            } else {
                wp_umbrella_debug_log("Theme '{$theme}' successfully updated");
                $newVersion = $this->getVersionFromThemeFile($theme);
                $trace->addTrace('state_set_completed', ['new_version' => $newVersion]);
                $stateManager->updateStatus($theme, $stateManager::STATUS_COMPLETED, [
                    'new_version' => $newVersion,
                ], 'theme');
            }

            // Cleanup lock and maintenance mode in all cases (success or error)
            delete_transient($lockKey);
            $maintenanceMode->toggleMaintenanceMode(false);
            $trace->addTrace('maintenance_mode_disabled');

            return $result;
        } catch (\Throwable $e) {
            delete_transient($lockKey);
            $trace->addTrace('exception', ['message' => $e->getMessage()]);
            wp_umbrella_debug_log("Theme '{$theme}' update exception: " . $e->getMessage());

            // Check if the update actually succeeded despite the exception
            $currentVersion = $this->getVersionFromThemeFile($theme);
            $versionActuallyChanged = isset($oldVersion) && !empty($currentVersion) && $currentVersion !== $oldVersion;

            if ($versionActuallyChanged) {
                $trace->addTrace('exception_but_version_changed', ['from' => $oldVersion, 'to' => $currentVersion]);
                wp_umbrella_debug_log("Theme '{$theme}' exception but version changed ({$oldVersion} → {$currentVersion}), treating as success");

                $stateManager->updateStatus($theme, $stateManager::STATUS_COMPLETED, [
                    'new_version' => $currentVersion,
                    'exception' => $e->getMessage(),
                ], 'theme');

                $maintenanceMode->toggleMaintenanceMode(false);

                return [
                    'status' => 'success',
                    'code' => 'success',
                    'message' => sprintf('The %s theme successfully updated', $theme),
                    'data' => true,
                ];
            }

            $requireBackup = isset($options['require_backup']) ? (bool) $options['require_backup'] : true;

            if ($requireBackup) {
                $trace->addTrace('rollback_started');
                $rollback = $this->performThemeRollback($theme, 'exception: ' . $e->getMessage());
                $trace->addTrace('rollback_done', ['status' => $rollback['status']]);
            } else {
                $rollback = ['status' => 'not_needed', 'reason' => null];
                wp_umbrella_debug_log("Theme '{$theme}' quick update exception, skipping rollback");
            }

            $stateManager->updateStatus($theme, $stateManager::STATUS_FAILED, [
                'error_code' => 'exception',
                'error_message' => $e->getMessage(),
                'rollback_status' => $rollback['status'],
            ], 'theme');

            $maintenanceMode->toggleMaintenanceMode(false);

            return [
                'status' => 'error',
                'code' => $rollback['status'] !== 'not_needed' ? $rollback['status'] : 'unknown_error',
                'message' => $e->getMessage(),
                'rollback_performed' => $rollback['status'] === 'rollback_performed',
                'rollback_reason' => $rollback['reason'],
                'restored_version' => isset($oldVersion) ? $oldVersion : null,
                'data' => ''
            ];
        }
    }

    /**
     * Register hooks to detect WP core's own rollback mechanism for themes.
     */
    protected function registerWpCoreRollbackDetection($theme, $stateManager)
    {
        add_filter('upgrader_install_package_result', function ($result, $hookExtra) use ($theme, $stateManager) {
            if (!is_wp_error($result)) {
                return $result;
            }

            if (empty($hookExtra['temp_backup'])) {
                return $result;
            }

            $backupSlug = isset($hookExtra['temp_backup']['slug']) ? $hookExtra['temp_backup']['slug'] : null;
            if ($backupSlug !== $theme) {
                return $result;
            }

            wp_umbrella_debug_log("Theme: WP core rollback scheduled for '{$theme}'");
            $stateManager->updateStatus($theme, $stateManager::STATUS_WP_CORE_ROLLBACK_SCHEDULED, [], 'theme');

            add_action('shutdown', function () use ($theme, $stateManager) {
                $themeDir = get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme;
                $wpCoreBackupDir = WP_CONTENT_DIR . '/upgrade-temp-backup/themes/' . $theme;

                if (is_dir($themeDir) && !is_dir($wpCoreBackupDir)) {
                    wp_umbrella_debug_log("Theme: WP core rollback succeeded for '{$theme}'");
                    $stateManager->updateStatus($theme, $stateManager::STATUS_WP_CORE_ROLLBACK_DONE, [], 'theme');
                } else {
                    wp_umbrella_debug_log("Theme: WP core rollback failed for '{$theme}'");
                    $stateManager->updateStatus($theme, $stateManager::STATUS_WP_CORE_ROLLBACK_FAILED, [], 'theme');
                }
            }, 50);

            return $result;
        }, 999, 2);
    }

    private function processUpgradeResult($theme, $response, $skin, $oldVersion)
    {
        // Check for skin result errors (like Plugin update does)
        if (is_wp_error($skin->result)) {
            $errorCode = $skin->result->get_error_code();
            wp_umbrella_debug_log("Theme '{$theme}' skin result error: {$errorCode} - " . $skin->result->get_error_message());

            if (in_array($errorCode, ['remove_old_failed', 'mkdir_failed_ziparchive', 'copy_failed_ziparchive'], true)) {
                return [
                    'status' => 'error',
                    'code' => 'remove_old_failed_or_mkdir_failed_ziparchive_error',
                    'message' => $skin->get_error_messages(),
                    'data' => ''
                ];
            }

            return [
                'status' => 'error',
                'code' => 'theme_upgrader_skin_result_error',
                'message' => $skin->result->get_error_message(),
                'data' => ''
            ];
        }

        // Check for skin errors
        if (is_wp_error($skin->get_errors()) && $skin->get_errors()->get_error_code()) {
            $errorCode = $skin->get_errors()->get_error_code();
            wp_umbrella_debug_log("Theme '{$theme}' skin error: {$errorCode} - " . $skin->get_error_messages());

            if (in_array($errorCode, ['remove_old_failed', 'mkdir_failed_ziparchive', 'copy_failed_ziparchive'], true)) {
                return [
                    'status' => 'error',
                    'code' => 'remove_old_failed_or_mkdir_failed_ziparchive_error',
                    'message' => $skin->get_error_messages(),
                    'data' => ''
                ];
            }

            return [
                'status' => 'error',
                'code' => 'theme_upgrader_skin_error',
                'message' => $skin->get_error_messages(),
                'data' => ''
            ];
        }

        // Check for filesystem errors
        if (false === $response) {
            global $wp_filesystem;

            $message = '';
            if ($wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
                $message = esc_html($wp_filesystem->errors->get_error_message());
            }

            wp_umbrella_debug_log("Theme '{$theme}' filesystem error: " . ($message ?: 'unable to connect'));
            return [
                'status' => 'error',
                'code' => 'unable_connect_filesystem',
                'message' => $message,
                'data' => ''
            ];
        }

        if (empty($response)) {
            wp_umbrella_debug_log("Theme '{$theme}' upgrade returned empty response");
            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => 'Upgrade failed.',
                'data' => ''
            ];
        }

        foreach ($response as $theme_tmp => $theme_info) {
            if (is_wp_error($theme_info) || empty($theme_info)) {
                wp_umbrella_debug_log("Theme '{$theme}' upgrader error: " . wp_json_encode($this->getError($theme_info)));
                return [
                    'status' => 'error',
                    'code' => 'theme_upgrader_error',
                    'message' => $this->getError($theme_info),
                    'data' => ''
                ];
            }
        }

        // Verify theme integrity after update
        $integrityCheck = $this->themeDirectoryExist($theme);
        if (!$integrityCheck['success']) {
            wp_umbrella_debug_log("Theme '{$theme}' integrity check failed: " . ($integrityCheck['code'] ?? 'unknown'));
            return [
                'status' => 'error',
                'code' => 'theme_integrity_check_failed',
                'message' => 'Theme update reported success but theme directory is invalid: ' . ($integrityCheck['code'] ?? 'unknown'),
                'data' => ''
            ];
        }

        // Verify version actually changed
        $newVersion = $this->getVersionFromThemeFile($theme);
        if ($oldVersion !== false && $newVersion !== false && $oldVersion === $newVersion) {
            wp_umbrella_debug_log("Theme '{$theme}' version unchanged after update: {$oldVersion}");
            return [
                'status' => 'error',
                'code' => 'theme_version_unchanged',
                'message' => sprintf('Theme update reported success but version unchanged (%s)', $oldVersion),
                'data' => ''
            ];
        }

        wp_umbrella_debug_log("Theme '{$theme}' successfully updated from " . ($oldVersion ?: 'unknown') . ' to ' . ($newVersion ?: 'unknown'));
        return [
            'status' => 'success',
            'code' => 'success',
            'message' => sprintf('The %s theme successfully updated', $theme),
            'data' => true
        ];
    }

    /**
     * Check theme integrity and rollback from temp backup if corrupted.
     *
     * @param string $theme Theme slug
     * @return array ['status' => 'not_needed'|'rollback_performed'|'rollback_failed', 'reason' => string|null]
     */
    protected function rollbackIfCorrupted($theme)
    {
        $themeDir = get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme;

        // Directory missing entirely
        if (!file_exists($themeDir) || !is_dir($themeDir)) {
            return $this->performThemeRollback($theme, 'directory missing');
        }

        // style.css missing — required for every WordPress theme
        if (!file_exists($themeDir . DIRECTORY_SEPARATOR . 'style.css')) {
            return $this->performThemeRollback($theme, 'style.css missing');
        }

        return ['status' => 'not_needed', 'reason' => null];
    }

    protected function performThemeRollback($theme, $reason)
    {
        try {
            wp_umbrella_debug_log("rollbackIfCorrupted: theme '{$theme}' {$reason}. Attempting rollback.");
            $rollbackResult = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'themes',
                'slug' => $theme,
            ]);
            $success = isset($rollbackResult['success']) && $rollbackResult['success'];
            wp_umbrella_debug_log('rollbackIfCorrupted: rollback ' . ($success ? 'succeeded' : 'failed') . " for theme '{$theme}'");
            return [
                'status' => $success ? 'rollback_performed' : 'rollback_failed',
                'reason' => $reason,
            ];
        } catch (\Throwable $e) {
            wp_umbrella_debug_log("rollbackIfCorrupted: exception during rollback for theme '{$theme}': " . $e->getMessage());
            return [
                'status' => 'rollback_failed',
                'reason' => $reason,
            ];
        }
    }

    public function activate($theme)
    {
        if (!wp_get_theme($theme)->exists()) {
            return [
                'status' => 'error',
                'code' => 'theme_not_installed',
                'message' => 'Theme is not installed.',
                'data' => []
            ];
        }

        $result = switch_theme($theme);

        return [
            'status' => 'success',
            'data' => $result
        ];
    }

    public function delete($theme)
    {
        if (!wp_get_theme($theme)->exists()) {
            return [
                'status' => 'error',
                'code' => 'theme_not_installed',
                'message' => 'Theme is not installed.',
                'data' => []
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        try {
            \delete_theme($theme);

            return [
                'status' => 'success',
                'code' => 'success',
                'message' => sprintf('The %s theme successfully deleted', $theme),
                'data' => []
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 'unknown_error',
            ];
        }
    }
}
