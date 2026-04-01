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
            wp_umbrella_debug_log("Plugin single update started for '{$plugin}'");

            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';

            // Store old version for verification
            $oldVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
            wp_umbrella_debug_log("Plugin '{$plugin}' current version: " . ($oldVersion ?: 'unknown'));

            $pluginInfoData = wp_umbrella_get_service('PluginsProvider')->getPlugin($plugin);

            $skin = new WP_Ajax_Upgrader_Skin();
            $skin->plugin_info = [
                'Name' => $pluginInfoData->name,
            ];
            $upgrader = new Plugin_Upgrader($skin);

            wp_umbrella_debug_log("Plugin '{$plugin}' running upgrader...");
            $response = $upgrader->upgrade($plugin);

            if (is_wp_error($skin->result)) {
                $errorCode = $skin->result->get_error_code();
                wp_umbrella_debug_log("Plugin '{$plugin}' skin result error: {$errorCode} - " . $skin->result->get_error_message());

                if (in_array($errorCode, ['remove_old_failed', 'mkdir_failed_ziparchive'], true)) {
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
                wp_umbrella_debug_log("Plugin '{$plugin}' skin error: remove_old_failed or mkdir_failed_ziparchive");
                return [
                    'status' => 'error',
                    'code' => 'remove_old_failed_or_mkdir_failed_ziparchive_error',
                    'message' => $skin->get_error_messages(),
                    'data' => $response
                ];
            } elseif ($skin->get_errors()->get_error_code()) {
                wp_umbrella_debug_log("Plugin '{$plugin}' skin error: " . $skin->get_errors()->get_error_code() . ' - ' . $skin->get_error_messages());
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

                wp_umbrella_debug_log("Plugin '{$plugin}' filesystem error: " . ($message ?: 'unable to connect'));
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
                wp_umbrella_debug_log("Plugin '{$plugin}' integrity check failed: " . ($integrityCheck['code'] ?? 'unknown'));
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
                wp_umbrella_debug_log("Plugin '{$plugin}' version unchanged after update: {$oldVersion}");
                return [
                    'status' => 'error',
                    'code' => 'plugin_version_unchanged',
                    'message' => sprintf('Plugin update reported success but version unchanged (%s)', $oldVersion),
                    'data' => ''
                ];
            }

            if ($plugin === 'woocommerce/woocommerce.php') {
                wp_umbrella_debug_log("Plugin '{$plugin}' running WooCommerce database update");
                wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
            }

            if ($plugin === 'elementor/elementor.php' || $plugin === 'elementor-pro/elementor-pro.php') {
                wp_umbrella_debug_log("Plugin '{$plugin}' running Elementor database update");
                wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
            }

            // Disable maintenance mode
            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            wp_umbrella_debug_log("Plugin '{$plugin}' successfully updated from {$oldVersion} to {$newVersion}");

            $data = [
                'status' => 'success',
                'code' => 'success',
                'message' => sprintf('The %s plugin successfully updated', $plugin),
                'data' => $response
            ];

            return $data;
        } catch (\Exception $e) {
            wp_umbrella_debug_log("Plugin '{$plugin}' update exception: " . $e->getMessage());
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

        if ($onlyAjax) { // If only ajax, we don't try to update by admin-ajax.php
            $tryAjax = false;
        }

        try {
            wp_umbrella_debug_log('Plugin bulk update started for: ' . implode(', ', (array)$plugins) . ' (onlyAjax: ' . ($onlyAjax ? 'true' : 'false') . ', tryAjax: ' . ($tryAjax ? 'true' : 'false') . ')');

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
                wp_umbrella_debug_log("Plugin '{$plugin}' current version: " . ($oldVersions[$plugin] ?: 'unknown'));
            }

            if (!$onlyAjax) { // If not only ajax, we try to update by bulk_upgrade
                wp_umbrella_debug_log('Plugin bulk update: running bulk_upgrade...');
                $skin = new WP_Ajax_Upgrader_Skin();
                $upgrader = new Plugin_Upgrader($skin);
                $response = $upgrader->bulk_upgrade($plugins);

                if (empty($response)) {
                    wp_umbrella_debug_log('Plugin bulk update: bulk_upgrade returned empty response');
                    return [
                        'status' => 'error',
                        'code' => 'unknown_error',
                        'data' => $response
                    ];
                }

                foreach ($response as $plugin_slug => $plugin_info) {
                    $return[$plugin_slug] = 'success';

                    if (!$plugin_info || is_wp_error($plugin_info)) {
                        wp_umbrella_debug_log("Plugin '{$plugin_slug}' bulk_upgrade error: " . wp_json_encode($this->getError($plugin_info)));

                        // Update failed — always rollback to restore a safe state
                        $slug = dirname($plugin_slug);
                        $rollbackStatus = $this->performRollback($slug, 'update failed');
                        $return[$plugin_slug] = $rollbackStatus;
                        continue;
                    }

                    // Verify plugin integrity after update (directory, main file, readme.txt)
                    $slug = dirname($plugin_slug);
                    $mainFile = basename($plugin_slug);
                    $rollbackStatus = $this->rollbackIfCorrupted($slug, $mainFile);

                    if ($rollbackStatus !== 'not_needed') {
                        $return[$plugin_slug] = $rollbackStatus;
                        continue;
                    }

                    // We'll need to get the new version of the plugin
                    $newVersions = [];
                    foreach ($plugins as $plugin) {
                        $newVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                    }

                    // Only try ajax if the version is the same (not updated)
                    if ($tryAjax && $oldVersions[$plugin_slug] === $newVersions[$plugin_slug]) { // Need to try ajax and the version is the same
                        wp_umbrella_debug_log("Plugin '{$plugin_slug}' version unchanged after bulk_upgrade ({$oldVersions[$plugin_slug]}), trying admin-ajax fallback...");
                        $result = $this->tryUpdateByAdminAjax($plugin_slug);
                        $return[$plugin_slug] = $result['code'];

                        $newVersions = [];
                        foreach ($plugins as $plugin) {
                            $newVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                        }
                    }

                    // Always include version metadata so the worker can verify the update
                    $return[$plugin_slug . '_old_version'] = $oldVersions[$plugin_slug];
                    $return[$plugin_slug . '_new_version'] = $newVersions[$plugin_slug];

                    // Final check: if version still unchanged after all attempts, mark as error
                    if ($oldVersions[$plugin_slug] !== false && $newVersions[$plugin_slug] !== false && $oldVersions[$plugin_slug] === $newVersions[$plugin_slug]) {
                        $return[$plugin_slug] = 'plugin_version_unchanged';
                        wp_umbrella_debug_log("Plugin '{$plugin_slug}' version still unchanged after all attempts: {$oldVersions[$plugin_slug]}");
                    } else {
                        wp_umbrella_debug_log("Plugin '{$plugin_slug}' successfully updated from " . ($oldVersions[$plugin_slug] ?: 'unknown') . ' to ' . ($newVersions[$plugin_slug] ?: 'unknown'));
                    }
                }
            } else {
                // No verification with old version because we only use ajax here
                foreach ($plugins as $plugin) {
                    wp_umbrella_debug_log("Plugin '{$plugin}' updating via admin-ajax only...");
                    $result = $this->tryUpdateByAdminAjax($plugin);
                    $return[$plugin] = $result['code'];

                    if ($return[$plugin] !== 'success') {
                        // Update failed — always rollback
                        $slug = dirname($plugin);
                        $rollbackStatus = $this->performRollback($slug, 'admin-ajax update failed');
                        $return[$plugin] = $rollbackStatus;
                    } else {
                        // Update succeeded — verify integrity
                        $slug = dirname($plugin);
                        $mainFile = basename($plugin);
                        $rollbackStatus = $this->rollbackIfCorrupted($slug, $mainFile);

                        if ($rollbackStatus !== 'not_needed') {
                            $return[$plugin] = $rollbackStatus;
                        } else {
                            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax update result: " . $return[$plugin]);
                        }
                    }

                    // Include version metadata so the worker can verify the update
                    $newVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                    $return[$plugin . '_old_version'] = $oldVersions[$plugin] ?? null;
                    $return[$plugin . '_new_version'] = $newVersion;
                }
            }

            // Determine overall status from individual plugin results
            $hasError = false;
            foreach ($return as $key => $value) {
                // Skip version metadata keys (plugin_old_version, plugin_new_version)
                if (strpos($key, '_old_version') !== false || strpos($key, '_new_version') !== false) {
                    continue;
                }
                if ($value !== 'success') {
                    $hasError = true;
                    break;
                }
            }

            // Only run third-party database upgrades if the update succeeded
            if (!$hasError) {
                if (in_array('woocommerce/woocommerce.php', $plugins)) {
                    wp_umbrella_debug_log('Running WooCommerce database update');
                    wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
                }

                if (in_array('elementor/elementor.php', $plugins) || in_array('elementor-pro/elementor-pro.php', $plugins)) {
                    wp_umbrella_debug_log('Running Elementor database update');
                    wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
                }
            }

            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            $finalResponse = [
                'status' => $hasError ? 'error' : 'success',
                'code' => $hasError ? 'plugin_update_failed' : 'success',
                'data' => $return
            ];

            wp_umbrella_debug_log('Plugin bulk update completed: ' . wp_json_encode($return));

            return $finalResponse;
        } catch (\Exception $e) {
            wp_umbrella_debug_log('Plugin bulk update exception: ' . $e->getMessage());
            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
                'data' => ''
            ];
        }
    }

    /**
     * Rollback a plugin from temp backup if its directory is missing, main file is absent,
     * or file count dropped significantly compared to the backup.
     *
     * @param string $pluginSlug Plugin directory name (e.g. "elementor")
     * @param string|null $mainFile Main plugin file basename (e.g. "elementor.php")
     * @return string 'not_needed' | 'rollback_performed' | 'rollback_failed'
     */
    public function rollbackIfCorrupted($pluginSlug, $mainFile = null)
    {
        $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $pluginSlug;

        // Directory missing entirely
        if (!file_exists($pluginDir) || !is_dir($pluginDir)) {
            return $this->performRollback($pluginSlug, 'directory missing');
        }

        // Main plugin file missing
        if ($mainFile !== null && !file_exists($pluginDir . DIRECTORY_SEPARATOR . $mainFile)) {
            return $this->performRollback($pluginSlug, "main plugin file missing ({$mainFile})");
        }

        // readme.txt missing — every WordPress.org plugin must have one
        if (!file_exists($pluginDir . DIRECTORY_SEPARATOR . 'readme.txt')) {
            return $this->performRollback($pluginSlug, 'readme.txt missing');
        }

        return 'not_needed';
    }

    protected function performRollback($pluginSlug, $reason)
    {
        try {
            wp_umbrella_debug_log("rollbackIfCorrupted: plugin '{$pluginSlug}' {$reason}. Attempting rollback.");
            $result = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'plugins',
                'slug' => $pluginSlug,
            ]);
            $success = isset($result['success']) && $result['success'];
            wp_umbrella_debug_log('rollbackIfCorrupted: rollback ' . ($success ? 'succeeded' : 'failed') . " for '{$pluginSlug}'");
            return $success ? 'rollback_performed' : 'rollback_failed';
        } catch (\Throwable $e) {
            wp_umbrella_debug_log("rollbackIfCorrupted: exception during rollback for '{$pluginSlug}': " . $e->getMessage());
            return 'rollback_failed';
        }
    }

    /**
     * @param string $file Plugin file
     */
    public function tryUpdateByAdminAjax($plugin)
    {
        wp_umbrella_debug_log("Plugin '{$plugin}' trying update via admin-ajax...");

        // Make post request.
        $response = $this->sendAdminRequest(
            $plugin
        );

        // If request not failed.
        if (!empty($response)) {
            $decoded = json_decode($response, true);
            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax response: " . ($decoded['code'] ?? 'unknown'));
            return $decoded;
        }

        wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax request failed (empty response)");
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

        if (is_wp_error($response)) {
            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax HTTP error: " . $response->get_error_message());
        } else {
            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax HTTP status: " . wp_remote_retrieve_response_code($response));
        }

        return false;
    }
}
