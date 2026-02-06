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
        // As a precaution, we advise you to move the plugin in all cases, as plugins are sometimes deleted.
        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => $theme,
            'src' => get_theme_root($theme),
            'dir' => 'themes'
        ]);
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

    public function update($theme)
    {
        try {
            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            // Store old version for verification
            $oldVersion = $this->getVersionFromThemeFile($theme);

            $this->generateSafeUpdateBackupTheme($theme);

            @ob_start();

            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            $response = $upgrader->bulk_upgrade([$theme]);

            @flush();
            @ob_clean();
            @ob_end_clean();

            // Check for skin result errors (like Plugin update does)
            if (is_wp_error($skin->result)) {
                $errorCode = $skin->result->get_error_code();
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

                return [
                    'status' => 'error',
                    'code' => 'unable_connect_filesystem',
                    'message' => $message,
                    'data' => ''
                ];
            }

            if (empty($response)) {
                return [
                    'status' => 'error',
                    'code' => 'unknown_error',
                    'message' => 'Upgrade failed.',
                    'data' => ''
                ];
            }

            foreach ($response as $theme_tmp => $theme_info) {
                if (is_wp_error($theme_info) || empty($theme_info)) {
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
                return [
                    'status' => 'error',
                    'code' => 'theme_version_unchanged',
                    'message' => sprintf('Theme update reported success but version unchanged (%s)', $oldVersion),
                    'data' => ''
                ];
            }

            // Disable maintenance mode
            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            $data = [
                'status' => 'success',
                'code' => 'success',
                'message' => sprintf('The %s theme successfully updated', $theme),
                'data' => true
            ];

            return $data;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
                'data' => ''
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
