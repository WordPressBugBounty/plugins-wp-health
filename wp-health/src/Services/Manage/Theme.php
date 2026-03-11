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

        try {
            wp_umbrella_debug_log("Theme update started for '{$theme}'");

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

            wp_umbrella_debug_log("Theme '{$theme}' creating temp backup");
            $backupResult = $this->generateSafeUpdateBackupTheme($theme);

            $requireBackup = isset($options['require_backup']) && $options['require_backup'];
            if ($requireBackup && !$backupResult['success']) {
                wp_umbrella_debug_log("Theme '{$theme}' backup failed: " . ($backupResult['code'] ?? 'unknown'));
                $maintenanceMode->toggleMaintenanceMode(false);

                return [
                    'status' => 'error',
                    'code' => 'temp_backup_required_failed',
                    'message' => sprintf('Temporary backup failed for theme %s: %s. Update aborted to ensure rollback safety.', $theme, $backupResult['code'] ?? 'unknown'),
                    'data' => ''
                ];
            }

            // Register shutdown handler to restore theme if PHP crashes mid-update
            // Same pattern as WordPress core WP_Upgrader::run() (shutdown actions are immune to PHP timeouts)
            $themeSlug = $theme;
            $themeRoot = get_theme_root($theme);

            register_shutdown_function(function () use ($themeSlug, $themeRoot) {
                $error = error_get_last();
                if ($error === null) {
                    return;
                }

                if (!in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                    return;
                }

                $themeDir = $themeRoot . DIRECTORY_SEPARATOR . $themeSlug;
                if (file_exists($themeDir) && is_dir($themeDir)) {
                    return;
                }

                try {
                    wp_umbrella_debug_log("Shutdown handler: fatal error detected, theme '{$themeSlug}' directory missing. Attempting rollback. Error: {$error['message']}");
                    $rollbackResult = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                        'dir' => 'themes',
                        'slug' => $themeSlug,
                    ]);
                    $success = isset($rollbackResult['success']) && $rollbackResult['success'];
                    wp_umbrella_debug_log('Shutdown handler: rollback ' . ($success ? 'succeeded' : 'failed') . " for theme '{$themeSlug}'");
                } catch (\Throwable $e) {
                    // Best effort - the worker-side CheckThemeDirectoryExist remains the final safety net
                }

                // Cleanup maintenance mode to prevent site staying locked after crash
                $maintenanceFile = ABSPATH . '.maintenance';
                if (file_exists($maintenanceFile)) {
                    @unlink($maintenanceFile);
                }
            });

            @ob_start();

            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);

            wp_umbrella_debug_log("Theme '{$theme}' running bulk_upgrade...");
            $response = $upgrader->bulk_upgrade([$theme]);

            @flush();
            @ob_clean();
            @ob_end_clean();

            $result = $this->processUpgradeResult($theme, $response, $skin, $oldVersion);

            if ($result['status'] === 'error') {
                wp_umbrella_debug_log("Theme '{$theme}' update failed: " . ($result['code'] ?? 'unknown') . " - " . ($result['message'] ?? ''));
                $this->attemptRollbackIfMissing($theme);
            } else {
                wp_umbrella_debug_log("Theme '{$theme}' successfully updated");
            }

            // Disable maintenance mode in all cases (success or error)
            $maintenanceMode->toggleMaintenanceMode(false);

            return $result;
        } catch (\Throwable $e) {
            wp_umbrella_debug_log("Theme '{$theme}' update exception: " . $e->getMessage());
            $this->attemptRollbackIfMissing($theme);
            $maintenanceMode->toggleMaintenanceMode(false);

            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
                'data' => ''
            ];
        }
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

        wp_umbrella_debug_log("Theme '{$theme}' successfully updated from " . ($oldVersion ?: 'unknown') . " to " . ($newVersion ?: 'unknown'));
        return [
            'status' => 'success',
            'code' => 'success',
            'message' => sprintf('The %s theme successfully updated', $theme),
            'data' => true
        ];
    }

    private function attemptRollbackIfMissing($theme)
    {
        $themeDir = get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme;
        if (file_exists($themeDir) && is_dir($themeDir)) {
            return;
        }

        try {
            wp_umbrella_debug_log("attemptRollbackIfMissing: theme '{$theme}' directory missing. Attempting rollback from backup.");
            $rollbackResult = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'themes',
                'slug' => $theme,
            ]);
            $success = isset($rollbackResult['success']) && $rollbackResult['success'];
            wp_umbrella_debug_log('attemptRollbackIfMissing: rollback ' . ($success ? 'succeeded' : 'failed') . " for theme '{$theme}'");
        } catch (\Throwable $e) {
            // Best effort - the worker-side CheckThemeDirectoryExist remains the final safety net
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
