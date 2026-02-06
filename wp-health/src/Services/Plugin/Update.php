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

            // Store old version for verification
            $oldVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);

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

            // Verify plugin integrity after update
            $integrityCheck = wp_umbrella_get_service('ManagePlugin')->directoryPluginExist($plugin);

            if (!$integrityCheck['success']) {
                return [
                    'status' => 'error',
                    'code' => 'plugin_integrity_check_failed',
                    'message' => 'Plugin update reported success but plugin directory is invalid: ' . ($integrityCheck['code'] ?? 'unknown'),
                    'data' => ''
                ];
            }

            // Verify version actually changed
            $newVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
            if ($oldVersion !== false && $newVersion !== false && $oldVersion === $newVersion) {
                return [
                    'status' => 'error',
                    'code' => 'plugin_version_unchanged',
                    'message' => sprintf('Plugin update reported success but version unchanged (%s)', $oldVersion),
                    'data' => ''
                ];
            }

            if ($plugin === 'woocommerce/woocommerce.php') {
                wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
            }

            if ($plugin === 'elementor/elementor.php' || $plugin === 'elementor-pro/elementor-pro.php') {
                wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
            }

            // Disable maintenance mode
            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            $data = [
                'status' => 'success',
                'code' => 'success',
                'message' => sprintf('The %s plugin successfully updated', $plugin),
                'data' => $response
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
        // Trace: log caller information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $callerInfo = [];
        foreach ($backtrace as $index => $trace) {
            $file = isset($trace['file']) ? basename($trace['file']) : 'N/A';
            $line = isset($trace['line']) ? $trace['line'] : 'N/A';
            $class = isset($trace['class']) ? $trace['class'] : '';
            $function = isset($trace['function']) ? $trace['function'] : '';
            $callerInfo[] = sprintf('#%d %s%s%s() in %s:%s', $index, $class, $class ? '::' : '', $function, $file, $line);
        }

        $onlyAjax = isset($options['only_ajax']) ? $options['only_ajax'] : false; // Try only by admin-ajax.php
        $tryAjax = isset($options['try_ajax']) ? $options['try_ajax'] : true; // For retry with admin-ajax.php if plugin update failed

        if ($onlyAjax) { // If only ajax, we don't try to update by admin-ajax.php
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

            $oldVersions = [];
            foreach ($plugins as $plugin) {
                $oldVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
            }

            if (!$onlyAjax) { // If not only ajax, we try to update by bulk_upgrade
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
                        continue;
                    }

                    // Verify plugin integrity after update
                    $integrityCheck = wp_umbrella_get_service('ManagePlugin')->directoryPluginExist($plugin_slug);

                    if (!$integrityCheck['success']) {
                        $return[$plugin_slug] = 'plugin_integrity_check_failed';
                        continue;
                    }

                    // We'll need to get the new version of the plugin
                    $newVersions = [];
                    foreach ($plugins as $plugin) {
                        $newVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                    }

                    // Only try ajax if the version is the same (not updated)
                    if ($tryAjax && $oldVersions[$plugin_slug] === $newVersions[$plugin_slug]) { // Need to try ajax and the version is the same
                        $result = $this->tryUpdateByAdminAjax($plugin_slug);
                        $return[$plugin_slug] = $result['code'];

                        $newVersions = [];
                        foreach ($plugins as $plugin) {
                            $newVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                        }
                    }

                    // Final check: if version still unchanged after all attempts, mark as error
                    if ($oldVersions[$plugin_slug] !== false && $newVersions[$plugin_slug] !== false && $oldVersions[$plugin_slug] === $newVersions[$plugin_slug]) {
                        $return[$plugin_slug] = 'plugin_version_unchanged';
                        $return[$plugin_slug . '_old_version'] = $oldVersions[$plugin_slug];
                        $return[$plugin_slug . '_new_version'] = $newVersions[$plugin_slug];
                    }
                }
            } else {
                // No verification with old version because we only use ajax here
                foreach ($plugins as $plugin) {
                    $result = $this->tryUpdateByAdminAjax($plugin);
                    $return[$plugin] = $result['code'];

                    $integrityCheck = wp_umbrella_get_service('ManagePlugin')->directoryPluginExist($plugin);
                    if (!$integrityCheck['success']) {
                        $return[$plugin] = 'plugin_integrity_check_failed';
                    }
                }
            }

            if (in_array('woocommerce/woocommerce.php', $plugins)) {
                wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
            }

            if (in_array('elementor/elementor.php', $plugins) || in_array('elementor-pro/elementor-pro.php', $plugins)) {
                wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
            }

            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            $finalResponse = [
                'status' => 'success',
                'code' => 'success',
                'data' => $return
            ];

            return $finalResponse;
        } catch (\Exception $e) {
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
            'headers' => [
                'X-Umbrella' => wp_umbrella_get_api_key(),
            ],
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
