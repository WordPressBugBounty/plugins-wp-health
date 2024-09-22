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

    public function update($theme)
    {
        try {
            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            $response = $upgrader->upgrade($theme);

            if (is_wp_error($skin->result)) {
                if (in_array($skin->result->get_error_code(), ['remove_old_failed', 'mkdir_failed_ziparchive'], true)) {
                    return [
                        'status' => 'error',
                        'code' => 'remove_old_failed_or_mkdir_failed_ziparchive_error',
                        'message' => $skin->get_error_messages(),
                        'data' => $response
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'code' => 'theme_upgrader_error',
                        'message' => $skin->result->get_error_message(),
                        'data' => $response
                    ];
                }

                return  [
                    'status' => 'error',
                    'code' => 'theme_upgrader_error',
                    'message' => '',
                    'data' => $response
                ];
            } elseif (in_array($skin->get_errors()->get_error_code(), ['remove_old_failed', 'mkdir_failed_ziparchive'], true)) {
                return [
                    'status' => 'error',
                    'code' => 'remove_old_failed_or_mkdir_failed_ziparchive_error',
                    'message' => $skin->get_error_messages(),
                    'data' => $response
                ];
            } elseif ($skin->get_errors()->get_error_code()) {
                return [
                    'status' => 'error',
                    'code' => 'theme_upgrader_skin_error',
                    'message' => $skin->get_error_messages(),
                    'data' => $response
                ];
            } elseif (false === $response) {
                global $wp_filesystem;

                $message = '';

                // Pass through the error from WP_Filesystem if one was raised.
                if ($wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
                    $message = esc_html($wp_filesystem->errors->get_error_message());
                }

                return [
                    'status' => 'error',
                    'code' => 'unable_connect_filesystem',
                    'message' => $message,
                    'data' => $response
                ];
            }

            $data = [
                'status' => 'success',
                'code' => 'success',
                'message' => sprintf('The %s theme successfully updated', $theme),
                'data' => $response
            ];

            return $data;
        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();

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
