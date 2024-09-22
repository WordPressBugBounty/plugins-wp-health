<?php
namespace WPUmbrella\Services\Plugin;

use WPUmbrella\Core\Update\Plugin\UpdaterSkin;
use WPUmbrella\Services\Manage\BaseManageUpdate;
use Automatic_Upgrader_Skin;
use Exception;
use Plugin_Upgrader;
use WP_Error;
use WP_Ajax_Upgrader_Skin;

class Update extends BaseManageUpdate
{
    const NAME_SERVICE = 'PluginUpdate';

    public function update($plugin)
    {
        try {
            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $pluginInfoData = wp_umbrella_get_service('PluginsProvider')->getPlugin($plugin);

            $skin = new WP_Ajax_Upgrader_Skin();
            $skin->plugin_info = [
                'Name' => $pluginInfoData->name,
            ];
            $upgrader = new Plugin_Upgrader($skin);
            $response = $upgrader->upgrade($plugin);

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
                        'code' => 'plugin_upgrader_error',
                        'message' => $skin->result->get_error_message(),
                        'data' => $response
                    ];
                }

                return  [
                    'status' => 'error',
                    'code' => 'plugin_upgrader_error',
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
                    'code' => 'plugin_upgrader_skin_error',
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

            if ($plugin === 'woocommerce/woocommerce.php') {
                wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
            }

            $data = [
                'status' => 'success',
                'code' => 'success',
                'message' => sprintf('The %s plugin successfully updated', $plugin),
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

    public function ithemesCompatibility()
    {
        // Check for the iThemes updater class
        if (empty($GLOBALS['ithemes_updater_path']) ||
            !file_exists($GLOBALS['ithemes_updater_path'] . '/settings.php')
        ) {
            return;
        }

        // Include iThemes updater
        require_once $GLOBALS['ithemes_updater_path'] . '/settings.php';

        // Check if the updater is instantiated
        if (empty($GLOBALS['ithemes-updater-settings'])) {
            return;
        }

        // Update the download link
        $GLOBALS['ithemes-updater-settings']->flush('forced');
    }

    /**
     * @param array[string] $plugins
     *    [
     *       [plugin-slug]
     *    ]
     * @return array
     *    [
     * 	  'status' => (string),
     * 	  'code' => (string),
     * 	  'data' => [
     * 		  [plugin] => (string)
     *        ...
     *   ]
     */
    public function bulkUpdate($plugins, $options = [])
    {
        $onlyAjax = isset($options['only_ajax']) ? $options['only_ajax'] : false; // Try only by admin-ajax.php
        $tryAjax = isset($options['try_ajax']) ? $options['try_ajax'] : true; // For retry with admin-ajax.php if plugin update failed

        if ($onlyAjax) {
            $tryAjax = false;
        }

        try {
            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            if (!is_array($plugins)) {
                $plugins = [$plugins];
            }

            if (!$onlyAjax) {
                $skin = new WP_Ajax_Upgrader_Skin();
                $upgrader = new Plugin_Upgrader($skin);
                $response = $upgrader->bulk_upgrade($plugins);

                if (empty($response)) {
                    return [
                        'status' => 'error',
                        'code' => 'unknown_error',
                        'data' => $response
                    ];
                }

                foreach ($response as $plugin_slug => $plugin_info) {
                    $return[$plugin_slug] = 'success';

                    if (!$plugin_info || is_wp_error($plugin_info)) {
                        $return[$plugin_slug] = $this->getError($plugin_info);
                    }

                    if ($tryAjax) {
                        $result = $this->tryUpdateByAdminAjax($plugin_slug);
                        $return[$plugin_slug] = $result['code'];
                    }
                }
            } else {
                foreach ($plugins as $plugin) {
                    $result = $this->tryUpdateByAdminAjax($plugin);
                    $return[$plugin] = $result['code'];
                }
            }

            if (in_array('woocommerce/woocommerce.php', $plugins)) {
                wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
            }

            if (in_array('elementor/elementor.php', $plugins) || in_array('elementor-pro/elementor-pro.php', $plugins)) {
                wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
            }

            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => $return
            ];
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

    /**
     * @param string $file Plugin file
     */
    public function tryUpdateByAdminAjax($plugin)
    {
        // Make post request.
        $response = $this->sendAdminRequest(
            $plugin
        );

        // If request not failed.
        if (!empty($response)) {
            // Get response body.
            return json_decode($response, true);
        }

        return [
            'status' => 'error',
            'code' => 'update_plugin_error',
            'message' => '',
            'data' => $response
        ];
    }

    protected function sendAdminRequest($plugin)
    {
        // Create nonce.
        $nonce = wp_create_nonce('wp_umbrella_update_admin_request');

        // Request arguments.
        $args = [
            'timeout' => 45,
            'cookies' => [],
            'sslverify' => false,
            'body' => [
                'action' => 'wp_umbrella_update_admin_request',
                'nonce' => $nonce,
                'plugin' => $plugin,
            ],
        ];

        // Set cookies if required.
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $name => $value) {
                $args['cookies'][] = new \WP_Http_Cookie(compact('name', 'value'));
            }
        }

        // Make post request.
        $response = wp_remote_post(admin_url('admin-ajax.php'), $args);

        // If request not failed.
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            // Get response body.
            return wp_remote_retrieve_body($response);
        }

        return false;
    }
}
