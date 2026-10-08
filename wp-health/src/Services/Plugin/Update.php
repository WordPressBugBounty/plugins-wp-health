<?php
namespace WPUmbrella\Services\Plugin;

use WPUmbrella\Core\Update\Plugin\UpdaterSkin;
use WPUmbrella\Helpers\Opcache;
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
            $upgradeContext = wp_umbrella_get_service('UpgradeContext');
            $upgradeContext->begin();

            try {
                $response = $upgrader->upgrade($plugin);
            } finally {
                $upgradeContext->end();
            }

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
                $unchangedCode = $this->resolveUnchangedVersionCode($plugin, $oldVersion);
                if ($unchangedCode === 'already_at_target') {
                    wp_umbrella_debug_log("Plugin '{$plugin}' already at target version: {$oldVersion}");
                    wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);
                    return [
                        'status' => 'success',
                        'code' => 'already_at_target',
                        'message' => sprintf('The %s plugin is already at the target version (%s)', $plugin, $oldVersion),
                        'data' => true,
                    ];
                }

                if ($unchangedCode === 'no_update_available') {
                    wp_umbrella_debug_log("Plugin '{$plugin}' has no update available after refresh: {$oldVersion}");
                    wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);
                    return [
                        'status' => 'error',
                        'code' => 'no_update_available',
                        'message' => sprintf('No update available for the %s plugin (%s)', $plugin, $oldVersion),
                        'data' => ''
                    ];
                }

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
        $requireBackup = isset($options['require_backup']) && $options['require_backup']; // Only safe updates should auto-rollback
        $providedUpdates = isset($options['provided_updates']) && is_array($options['provided_updates'])
            ? $options['provided_updates']
            : [];
        $lockToken = isset($options['lock_token']) && is_string($options['lock_token']) && $options['lock_token'] !== ''
            ? $options['lock_token']
            : null;
        $trace = wp_umbrella_get_service('RequestTrace');

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
            $oldFingerprints = [];
            $transientNewVersions = [];
            $transientPackageHosts = [];
            foreach ($plugins as $plugin) {
                $oldVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                $oldFingerprints[$plugin] = $this->fingerprintMainFile($plugin);
                wp_umbrella_debug_log("Plugin '{$plugin}' current version: " . ($oldVersions[$plugin] ?: 'unknown'));
            }

            if (!$onlyAjax) { // If not only ajax, we try to update by bulk_upgrade
                $transientForTrace = get_site_transient('update_plugins');

                if (!empty($providedUpdates)) {
                    if (!is_object($transientForTrace)) {
                        $transientForTrace = new \stdClass();
                        $transientForTrace->response = [];
                    }
                    if (!isset($transientForTrace->response) || !is_array($transientForTrace->response)) {
                        $transientForTrace->response = [];
                    }

                    $seeded = false;
                    foreach ($plugins as $pluginToSeed) {
                        $provided = isset($providedUpdates[$pluginToSeed]) ? $providedUpdates[$pluginToSeed] : null;
                        $package = is_array($provided) && !empty($provided['package']) ? $provided['package'] : null;

                        if (!$package || isset($transientForTrace->response[$pluginToSeed])) {
                            continue;
                        }

                        $entry = new \stdClass();
                        $entry->slug = explode('/', $pluginToSeed)[0];
                        $entry->plugin = $pluginToSeed;
                        $entry->new_version = isset($provided['new_version']) ? $provided['new_version'] : '';
                        $entry->package = $package;
                        $entry->url = isset($provided['url']) ? $provided['url'] : false;

                        $transientForTrace->response[$pluginToSeed] = $entry;
                        $seeded = true;
                        $trace->addTrace('provided_update_seeded', [
                            'plugin' => $pluginToSeed,
                            'new_version' => $entry->new_version,
                            'package_host' => parse_url($package, PHP_URL_HOST),
                        ]);
                    }

                    if ($seeded) {
                        set_site_transient('update_plugins', $transientForTrace);
                    }
                }

                foreach ($plugins as $pluginForTrace) {
                    $entryForTrace = is_object($transientForTrace)
                        ? ($transientForTrace->response[$pluginForTrace] ?? null)
                        : null;
                    $hasPackage = is_object($entryForTrace) && !empty($entryForTrace->package);
                    $packageHost = $hasPackage ? parse_url($entryForTrace->package, PHP_URL_HOST) : null;
                    $transientNewVersions[$pluginForTrace] = is_object($entryForTrace) ? ($entryForTrace->new_version ?? null) : null;
                    $transientPackageHosts[$pluginForTrace] = $packageHost;
                    $trace->addTrace('transient_state', [
                        'plugin' => $pluginForTrace,
                        'has_entry' => is_object($entryForTrace),
                        'new_version' => $transientNewVersions[$pluginForTrace],
                        'package' => $hasPackage ? 'present' : 'missing',
                        'package_host' => $packageHost,
                        'installed_version' => $oldVersions[$pluginForTrace] ?? null,
                    ]);
                }

                $missingFromTransient = [];
                foreach ($plugins as $pluginForTrace) {
                    $entryForTrace = is_object($transientForTrace)
                        ? ($transientForTrace->response[$pluginForTrace] ?? null)
                        : null;
                    if (!is_object($entryForTrace)) {
                        $missingFromTransient[] = $pluginForTrace;
                    }
                }

                if (!empty($missingFromTransient) && function_exists('wp_update_plugins')) {
                    delete_site_transient('update_plugins');
                    wp_update_plugins();
                    $transientForTrace = get_site_transient('update_plugins');
                    $trace->addTrace('update_plugins_refreshed', ['missing_before' => count($missingFromTransient)]);

                    foreach ($missingFromTransient as $pluginForTrace) {
                        $entryForTrace = is_object($transientForTrace)
                            ? ($transientForTrace->response[$pluginForTrace] ?? null)
                            : null;
                        $hasPackage = is_object($entryForTrace) && !empty($entryForTrace->package);
                        $transientNewVersions[$pluginForTrace] = is_object($entryForTrace) ? ($entryForTrace->new_version ?? null) : null;
                        $transientPackageHosts[$pluginForTrace] = $hasPackage ? parse_url($entryForTrace->package, PHP_URL_HOST) : null;
                        $trace->addTrace('transient_state_recheck', [
                            'plugin' => $pluginForTrace,
                            'has_entry' => is_object($entryForTrace),
                            'new_version' => $transientNewVersions[$pluginForTrace],
                            'package' => $hasPackage ? 'present' : 'missing',
                        ]);
                    }
                }

                $pluginsWithUpdate = [];
                foreach ($plugins as $pluginToFilter) {
                    $entryToFilter = is_object($transientForTrace)
                        ? ($transientForTrace->response[$pluginToFilter] ?? null)
                        : null;

                    if (is_object($entryToFilter)) {
                        $pluginsWithUpdate[] = $pluginToFilter;
                        continue;
                    }

                    wp_umbrella_debug_log("Plugin '{$pluginToFilter}' has no update available in update_plugins transient, skipping");
                    $trace->addTrace('no_update_available', ['plugin' => $pluginToFilter]);
                    $return[$pluginToFilter] = 'no_update_available';
                    $return[$pluginToFilter . '_skip_rollback'] = true;
                    $return[$pluginToFilter . '_old_version'] = $oldVersions[$pluginToFilter];
                    $return[$pluginToFilter . '_new_version'] = $oldVersions[$pluginToFilter];
                }

                $response = [];
                if (!empty($pluginsWithUpdate)) {
                    wp_umbrella_debug_log('Plugin bulk update: running bulk_upgrade...');
                    $skin = new WP_Ajax_Upgrader_Skin();
                    $upgrader = new Plugin_Upgrader($skin);
                    $trace->addTrace('wp_bulk_upgrade_call');
                    $upgradeContext = wp_umbrella_get_service('UpgradeContext');
                    $upgradeContext->begin();

                    try {
                        $response = $upgrader->bulk_upgrade($pluginsWithUpdate);
                    } finally {
                        $upgradeContext->end();
                    }
                    $trace->addTrace('wp_bulk_upgrade_returned', ['empty' => empty($response)]);
                }

                if (!empty($pluginsWithUpdate) && empty($response)) {
                    wp_umbrella_debug_log('Plugin bulk update: bulk_upgrade returned empty response');
                    $trace->addTrace('wp_bulk_upgrade_empty_response');
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
                        $trace->addTrace('wp_bulk_upgrade_error', [
                            'plugin' => $plugin_slug,
                            'error' => wp_json_encode($this->getError($plugin_info)),
                        ]);

                        $wpError = $this->describeUpgradeError($plugin_info, $skin);
                        if ($this->isFailedBeforeWriting($wpError['code']) && $this->isPluginUntouched($plugin_slug, $oldFingerprints[$plugin_slug] ?? null, $oldVersions[$plugin_slug])) {
                            $this->markUpdateFailedUntouched($return, $plugin_slug, $wpError, $oldVersions[$plugin_slug]);
                            continue;
                        }

                        if ($requireBackup) {
                            // Safe update — rollback to restore a safe state
                            $slug = dirname($plugin_slug);
                            $rollback = $this->performRollback($slug, 'update failed', $lockToken, basename($plugin_slug));
                            $return[$plugin_slug] = $rollback['status'];
                            $return[$plugin_slug . '_rollback_reason'] = $rollback['reason'];
                        } else {
                            // Quick update — no auto-rollback
                            $return[$plugin_slug] = 'update_failed';
                            wp_umbrella_debug_log("Plugin '{$plugin_slug}' quick update failed, skipping rollback");
                        }
                        continue;
                    }

                    $trace->addTrace('wp_bulk_upgrade_success', ['plugin' => $plugin_slug]);

                    if ($requireBackup) {
                        // Safe update — verify plugin integrity after update (directory, main file, readme.txt)
                        $slug = dirname($plugin_slug);
                        $mainFile = basename($plugin_slug);
                        $trace->addTrace('integrity_check_started');
                        $rollback = $this->rollbackIfCorrupted($slug, $mainFile, $lockToken);

                        if ($rollback['status'] !== 'not_needed') {
                            $trace->addTrace('integrity_check_failed', ['reason' => $rollback['reason'], 'rollback' => $rollback['status']]);
                            $return[$plugin_slug] = $rollback['status'];
                            $return[$plugin_slug . '_rollback_reason'] = $rollback['reason'];
                            continue;
                        }
                        $trace->addTrace('integrity_check_passed');
                    } elseif (!$this->isPluginPresent($plugin_slug)) {
                        $this->markPluginMissingAfterUpdate($return, $plugin_slug, $oldVersions[$plugin_slug]);
                        continue;
                    }

                    // We'll need to get the new version of the plugin
                    $newVersions = [];
                    foreach ($plugins as $plugin) {
                        $newVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                    }
                    $postBulkFingerprint = $this->fingerprintMainFile($plugin_slug);
                    $postBulkDiff = $this->fingerprintDiff($oldFingerprints[$plugin_slug] ?? null, $postBulkFingerprint);

                    // Only try ajax if the version is the same (not updated)
                    if ($tryAjax && $oldVersions[$plugin_slug] === $newVersions[$plugin_slug]) { // Need to try ajax and the version is the same
                        if (!$postBulkDiff['mtime_changed'] && !$postBulkDiff['size_changed'] && !$postBulkDiff['hash_changed']) {
                            wp_umbrella_debug_log("Plugin '{$plugin_slug}' bulk_upgrade left the main file untouched, skipping admin-ajax fallback");
                            $trace->addTrace('ajax_fallback_skipped_no_op', ['plugin' => $plugin_slug]);
                        } else {
                            wp_umbrella_debug_log("Plugin '{$plugin_slug}' version unchanged after bulk_upgrade ({$oldVersions[$plugin_slug]}), trying admin-ajax fallback...");
                            $trace->addTrace('ajax_fallback_started', ['reason' => 'version_unchanged']);
                            $result = $this->tryUpdateByAdminAjax($plugin_slug, $lockToken);
                            if ($this->isAdminAjaxStillRunning($result)) {
                                return $this->adminAjaxStillRunningResponse($plugin_slug, $oldVersions[$plugin_slug]);
                            }
                            $return[$plugin_slug] = $result['code'];
                            $trace->addTrace('ajax_fallback_done', ['code' => $result['code']]);

                            $newVersions = [];
                            foreach ($plugins as $plugin) {
                                $newVersions[$plugin] = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                            }
                        }
                    }

                    // Version metadata
                    $return[$plugin_slug . '_old_version'] = $oldVersions[$plugin_slug];
                    $return[$plugin_slug . '_new_version'] = $newVersions[$plugin_slug];

                    // Final check: if version still unchanged after all attempts, mark as error
                    if ($oldVersions[$plugin_slug] !== false && $newVersions[$plugin_slug] !== false && $oldVersions[$plugin_slug] === $newVersions[$plugin_slug]) {
                        $unchangedCode = $this->resolveUnchangedVersionCode($plugin_slug, $oldVersions[$plugin_slug]);
                        if ($unchangedCode === 'already_at_target') {
                            $return[$plugin_slug] = 'success';
                            $return[$plugin_slug . '_already_at_target'] = true;
                            $trace->addTrace('plugin_already_at_target', ['plugin' => $plugin_slug, 'version' => $oldVersions[$plugin_slug]]);
                            wp_umbrella_debug_log("Plugin '{$plugin_slug}' already at target version: {$oldVersions[$plugin_slug]}");
                        } elseif ($unchangedCode === 'no_update_available') {
                            $return[$plugin_slug] = 'no_update_available';
                            $return[$plugin_slug . '_skip_rollback'] = true;
                            $trace->addTrace('no_update_available_after_refresh', ['plugin' => $plugin_slug, 'version' => $oldVersions[$plugin_slug]]);
                            wp_umbrella_debug_log("Plugin '{$plugin_slug}' has no update available after refresh: {$oldVersions[$plugin_slug]}");
                        } else {
                            $return[$plugin_slug] = 'plugin_version_unchanged';
                            $return[$plugin_slug . '_skip_rollback'] = true;
                            $finalFingerprint = $this->fingerprintMainFile($plugin_slug);
                            $finalDiff = $this->fingerprintDiff($oldFingerprints[$plugin_slug] ?? null, $finalFingerprint);
                            $trace->addTrace('version_unchanged_after_all_attempts_failed', [
                                'plugin' => $plugin_slug,
                                'installed_version' => $oldVersions[$plugin_slug],
                                'mtime_changed' => $finalDiff['mtime_changed'],
                                'size_changed' => $finalDiff['size_changed'],
                                'hash_changed' => $finalDiff['hash_changed'],
                                'transient_new_version' => $transientNewVersions[$plugin_slug] ?? null,
                                'transient_package_host' => $transientPackageHosts[$plugin_slug] ?? null,
                            ]);
                            wp_umbrella_debug_log("Plugin '{$plugin_slug}' version still unchanged after all attempts: {$oldVersions[$plugin_slug]}");
                        }
                    } else {
                        $trace->addTrace('version_changed', ['from' => $oldVersions[$plugin_slug], 'to' => $newVersions[$plugin_slug]]);
                        wp_umbrella_debug_log("Plugin '{$plugin_slug}' successfully updated from " . ($oldVersions[$plugin_slug] ?: 'unknown') . ' to ' . ($newVersions[$plugin_slug] ?: 'unknown'));
                    }
                }
            } else {
                // No verification with old version because we only use ajax here
                foreach ($plugins as $plugin) {
                    wp_umbrella_debug_log("Plugin '{$plugin}' updating via admin-ajax only...");
                    $trace->addTrace('ajax_only_started', ['plugin' => $plugin]);
                    $result = $this->tryUpdateByAdminAjax($plugin, $lockToken);
                    if ($this->isAdminAjaxStillRunning($result)) {
                        return $this->adminAjaxStillRunningResponse($plugin, $oldVersions[$plugin]);
                    }
                    $return[$plugin] = $result['code'];
                    $trace->addTrace('ajax_only_done', ['code' => $result['code']]);

                    $childError = $return[$plugin] !== 'success' ? $this->untouchedErrorFromAjaxResult($plugin, $result) : null;
                    if ($childError !== null && $this->isPluginUntouched($plugin, $oldFingerprints[$plugin] ?? null, $oldVersions[$plugin])) {
                        $this->markUpdateFailedUntouched($return, $plugin, $childError, $oldVersions[$plugin]);
                        continue;
                    }

                    if ($this->childPluginCode($plugin, $result) === 'no_update_available' && $this->isPluginUntouched($plugin, $oldFingerprints[$plugin] ?? null, $oldVersions[$plugin])) {
                        $trace->addTrace('no_update_available', ['plugin' => $plugin]);
                        $return[$plugin] = 'no_update_available';
                        $return[$plugin . '_skip_rollback'] = true;
                        $return[$plugin . '_old_version'] = $oldVersions[$plugin];
                        $return[$plugin . '_new_version'] = $oldVersions[$plugin];
                        continue;
                    }

                    if ($return[$plugin] !== 'success') {
                        if ($requireBackup) {
                            // Safe update — rollback
                            $slug = dirname($plugin);
                            $installWritten = !$this->isPluginUntouched($plugin, $oldFingerprints[$plugin] ?? null, $oldVersions[$plugin]);
                            $rollback = $this->performRollback($slug, 'admin-ajax update failed', $lockToken, basename($plugin));
                            $return[$plugin] = $rollback['status'];
                            $return[$plugin . '_rollback_reason'] = $rollback['reason'];
                            if ($installWritten) {
                                $return[$plugin . '_install_written'] = true;
                            }
                        } else {
                            // Quick update — no auto-rollback
                            wp_umbrella_debug_log("Plugin '{$plugin}' quick update (admin-ajax) failed, skipping rollback");
                        }
                    } else if ($requireBackup) {
                        // Safe update — verify integrity
                        $slug = dirname($plugin);
                        $mainFile = basename($plugin);
                        $trace->addTrace('integrity_check_started');
                        $rollback = $this->rollbackIfCorrupted($slug, $mainFile, $lockToken);

                        if ($rollback['status'] !== 'not_needed') {
                            $trace->addTrace('integrity_check_failed', ['reason' => $rollback['reason'], 'rollback' => $rollback['status']]);
                            $return[$plugin] = $rollback['status'];
                            $return[$plugin . '_rollback_reason'] = $rollback['reason'];
                            $return[$plugin . '_install_written'] = true;
                        } else {
                            $trace->addTrace('integrity_check_passed');
                            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax update result: " . $return[$plugin]);
                        }
                    } else {
                        wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax update result: " . $return[$plugin]);
                    }

                    // Version metadata
                    $newVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
                    $return[$plugin . '_old_version'] = $oldVersions[$plugin] ?? null;
                    $return[$plugin . '_new_version'] = $newVersion;
                }
            }

            // Determine overall status from individual plugin results
            $hasError = false;
            foreach ($return as $key => $value) {
                // Skip version metadata keys (plugin_old_version, plugin_new_version)
                if (strpos($key, '_old_version') !== false || strpos($key, '_new_version') !== false || strpos($key, '_skip_rollback') !== false || strpos($key, '_already_at_target') !== false || strpos($key, '_wp_error_') !== false || strpos($key, '_install_written') !== false) {
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
     * @param string $pluginSlug Plugin directory name (e.g. "elementor")
     * @param string|null $mainFile Main plugin file basename (e.g. "elementor.php")
     * @param string|null $lockToken
     * @return array ['status' => 'not_needed'|'rollback_performed'|'rollback_failed', 'reason' => string]
     */
    public function rollbackIfCorrupted($pluginSlug, $mainFile = null, $lockToken = null)
    {
        $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $pluginSlug;
        $trace = wp_umbrella_get_service('RequestTrace');

        // Directory missing entirely
        if (!file_exists($pluginDir) || !is_dir($pluginDir)) {
            $trace->addTrace('plugin_dir_missing', ['slug' => $pluginSlug]);
            return $this->performRollback($pluginSlug, 'directory missing', $lockToken, $mainFile);
        }

        // Main plugin file missing
        if ($mainFile !== null && !file_exists($pluginDir . DIRECTORY_SEPARATOR . $mainFile)) {
            $trace->addTrace('plugin_main_file_missing', ['slug' => $pluginSlug, 'file' => $mainFile]);
            return $this->performRollback($pluginSlug, "main plugin file missing ({$mainFile})", $lockToken, $mainFile);
        }

        return ['status' => 'not_needed', 'reason' => null];
    }

    protected function performRollback($pluginSlug, $reason, $lockToken = null, $mainFile = null)
    {
        $trace = wp_umbrella_get_service('RequestTrace');

        try {
            $trace->addTrace('rollback_started', ['slug' => $pluginSlug, 'reason' => $reason]);
            wp_umbrella_debug_log("rollbackIfCorrupted: plugin '{$pluginSlug}' {$reason}. Attempting rollback.");
            $result = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'plugins',
                'slug' => $pluginSlug,
                'main_file' => $mainFile,
            ]);
            $success = isset($result['success']) && $result['success'];
            $trace->addTrace($success ? 'rollback_succeeded' : 'rollback_failed', array_merge([
                'slug' => $pluginSlug,
                'code' => $result['code'] ?? 'unknown',
            ], isset($result['diagnostic']) && is_array($result['diagnostic']) ? $result['diagnostic'] : []));
            wp_umbrella_debug_log('rollbackIfCorrupted: rollback ' . ($success ? 'succeeded' : 'failed') . " for '{$pluginSlug}'");
            if ($success && $lockToken !== null) {
                wp_umbrella_get_service('UpdateLock')->release($pluginSlug, $lockToken);
            }
            return [
                'status' => $success ? 'rollback_performed' : 'rollback_failed',
                'reason' => $reason,
            ];
        } catch (\Throwable $e) {
            $trace->addTrace('rollback_exception', ['slug' => $pluginSlug, 'error' => $e->getMessage()]);
            wp_umbrella_debug_log("rollbackIfCorrupted: exception during rollback for '{$pluginSlug}': " . $e->getMessage());
            return [
                'status' => 'rollback_failed',
                'reason' => $reason,
            ];
        }
    }

    /**
     * @param string $pluginFile Like "hello-dolly/hello.php"
     */
    protected function isPluginPresent($pluginFile)
    {
        $pluginDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($pluginFile);
        $mainFile = $pluginDir . DIRECTORY_SEPARATOR . basename($pluginFile);
        @clearstatcache(true, $pluginDir);
        @clearstatcache(true, $mainFile);

        return is_dir($pluginDir) && file_exists($mainFile);
    }

    /**
     * @param string $pluginFile Like "hello-dolly/hello.php"
     * @param array|null $oldFingerprint From fingerprintMainFile() before the update
     * @param string|false $oldVersion
     */
    protected function isPluginUntouched($pluginFile, $oldFingerprint, $oldVersion)
    {
        if (!$this->isPluginPresent($pluginFile)) {
            return false;
        }

        if ($oldFingerprint !== null) {
            $diff = $this->fingerprintDiff($oldFingerprint, $this->fingerprintMainFile($pluginFile));

            return !$diff['mtime_changed'] && !$diff['size_changed'] && !$diff['hash_changed'];
        }

        if ($oldVersion === false || $oldVersion === null) {
            return false;
        }

        return wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($pluginFile) === $oldVersion;
    }

    /**
     * @param string $code WP_Error code of the failed upgrade
     */
    protected function isFailedBeforeWriting($code)
    {
        $codes = [
            'download_failed',
            'no_package',
            'http_no_url',
            'http_404',
            'incompatible_archive',
            'empty_archive',
            'no_credentials',
            'fs_unavailable',
            'fs_error',
        ];

        if (in_array($code, $codes, true)) {
            return true;
        }

        return is_string($code) && preg_match('/(_expired|licen[cs]e)/i', $code) === 1;
    }

    /**
     * @param string $plugin Like "hello-dolly/hello.php"
     * @param mixed $result
     * @return array|null ['code' => string, 'message' => string]
     */
    protected function untouchedErrorFromAjaxResult($plugin, $result)
    {
        if (!is_array($result) || !isset($result['data']) || !is_array($result['data'])) {
            return null;
        }

        $data = $result['data'];

        if (!isset($data[$plugin]) || $data[$plugin] !== 'update_failed_untouched') {
            return null;
        }

        $code = isset($data[$plugin . '_wp_error_code']) && is_string($data[$plugin . '_wp_error_code']) && $data[$plugin . '_wp_error_code'] !== ''
            ? $data[$plugin . '_wp_error_code']
            : 'unknown_error';

        return [
            'code' => $code,
            'message' => $this->stringifyMessage(isset($data[$plugin . '_wp_error_message']) ? $data[$plugin . '_wp_error_message'] : ''),
        ];
    }

    /**
     * @return array ['code' => string, 'message' => string]
     */
    protected function describeUpgradeError($pluginInfo, $skin)
    {
        $error = is_wp_error($pluginInfo) ? $pluginInfo : null;

        if ($error === null && is_object($skin) && method_exists($skin, 'get_errors') && is_wp_error($skin->get_errors()) && $skin->get_errors()->get_error_code()) {
            $error = $skin->get_errors();
        }

        if ($error === null) {
            return ['code' => 'unknown_error', 'message' => ''];
        }

        return [
            'code' => (string) $error->get_error_code(),
            'message' => $this->stringifyMessage($error->get_error_message()),
        ];
    }

    protected function stringifyMessage($message)
    {
        if (!is_string($message)) {
            $message = wp_json_encode($message);
        }

        return trim(strip_tags((string) $message));
    }

    protected function markPluginMissingAfterUpdate(&$return, $pluginFile, $oldVersion)
    {
        wp_umbrella_get_service('RequestTrace')->addTrace('plugin_missing_after_update', ['plugin' => $pluginFile]);

        $return[$pluginFile] = 'plugin_missing_after_update';
        $return[$pluginFile . '_skip_rollback'] = true;
        $return[$pluginFile . '_old_version'] = $oldVersion;
        $return[$pluginFile . '_new_version'] = null;
    }

    protected function markUpdateFailedUntouched(&$return, $pluginFile, $wpError, $oldVersion)
    {
        wp_umbrella_get_service('RequestTrace')->addTrace('update_failed_untouched', [
            'plugin' => $pluginFile,
            'wp_error_code' => $wpError['code'],
        ]);
        wp_umbrella_debug_log("Plugin '{$pluginFile}' update failed ({$wpError['code']}), plugin left untouched, skipping rollback");

        $return[$pluginFile] = 'update_failed_untouched';
        $return[$pluginFile . '_skip_rollback'] = true;
        $return[$pluginFile . '_wp_error_code'] = $wpError['code'];
        $return[$pluginFile . '_wp_error_message'] = $wpError['message'];
        $return[$pluginFile . '_old_version'] = $oldVersion;
        $return[$pluginFile . '_new_version'] = $oldVersion;
    }

    /**
     * @param string $plugin Plugin file
     * @param string|null $lockToken
     */
    public function tryUpdateByAdminAjax($plugin, $lockToken = null)
    {
        wp_umbrella_debug_log("Plugin '{$plugin}' trying update via admin-ajax...");

        $operation = $this->delegateToAdminAjax($plugin, $lockToken);

        // Make post request.
        $response = $this->sendAdminRequest(
            $plugin,
            $operation !== null ? ['lock_token' => $lockToken, 'operation' => $operation] : []
        );

        $decoded = !empty($response) ? json_decode($response, true) : null;

        if (is_array($decoded) && !empty($decoded['_trace_child']) && is_array($decoded['_trace_child'])) {
            $trace = wp_umbrella_get_service('RequestTrace');
            foreach ($decoded['_trace_child'] as $entry) {
                $meta = isset($entry['meta']) ? $entry['meta'] : [];
                $meta['child_ms'] = isset($entry['elapsed_ms']) ? $entry['elapsed_ms'] : null;
                $trace->addTrace('ajax_child:' . (isset($entry['label']) ? $entry['label'] : 'unknown'), $meta);
            }
            unset($decoded['_trace_child']);
        }

        if ($operation !== null && !$this->isAdminAjaxChildAnswer($decoded)) {
            $settled = $this->settleAdminAjaxChild($plugin, $lockToken, $operation);

            if ($settled['status'] === UpdateLock::DELEGATION_DONE) {
                return $settled['result'];
            }

            if ($settled['status'] !== UpdateLock::DELEGATION_NOT_STARTED) {
                return [
                    'status' => 'in_progress',
                    'code' => 'update_in_progress_self',
                    'message' => '',
                    'data' => '',
                ];
            }
        }

        // If request not failed.
        if (!empty($response)) {
            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax response: " . (is_array($decoded) && isset($decoded['code']) ? $decoded['code'] : 'unknown'));

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

    /**
     * @param string $plugin
     * @param string|null $lockToken
     * @return string|null
     */
    protected function delegateToAdminAjax($plugin, $lockToken)
    {
        if (!is_string($lockToken) || $lockToken === '') {
            return null;
        }

        $operation = wp_generate_password(32, false, false);

        if (!wp_umbrella_get_service('UpdateLock')->delegate(dirname($plugin), $lockToken, $operation)) {
            return null;
        }

        return $operation;
    }

    /**
     * @param string $plugin
     * @param mixed $result
     * @return string|null
     */
    protected function childPluginCode($plugin, $result)
    {
        if (!is_array($result) || !isset($result['data']) || !is_array($result['data']) || !isset($result['data'][$plugin]) || !is_string($result['data'][$plugin])) {
            return null;
        }

        return $result['data'][$plugin];
    }

    /**
     * @param mixed $decoded
     */
    protected function isAdminAjaxChildAnswer($decoded)
    {
        return is_array($decoded) && isset($decoded['status'], $decoded['code']) && is_string($decoded['code']) && isset($decoded['data']) && is_array($decoded['data']);
    }

    /**
     * @param string $plugin
     * @param string $lockToken
     * @param string $operation
     * @return array ['status' => string, 'result' => array|null]
     */
    protected function settleAdminAjaxChild($plugin, $lockToken, $operation)
    {
        $lock = wp_umbrella_get_service('UpdateLock');
        $waits = $this->adminAjaxSettleSeconds();

        for ($attempt = 0; ; $attempt++) {
            $settled = $lock->settleDelegation(dirname($plugin), $lockToken, $operation);

            if ($settled['status'] === UpdateLock::DELEGATION_RUNNING && $attempt >= $waits) {
                $settled = $lock->detachDelegation(dirname($plugin), $lockToken, $operation);
            }

            if ($settled['status'] !== UpdateLock::DELEGATION_RUNNING || $attempt >= $waits) {
                wp_umbrella_get_service('RequestTrace')->addTrace('ajax_child_settled', ['plugin' => $plugin, 'status' => $settled['status']]);

                return $settled;
            }

            $this->waitForAdminAjaxChild();
        }
    }

    protected function adminAjaxSettleSeconds()
    {
        return 30;
    }

    protected function waitForAdminAjaxChild()
    {
        sleep(1);
    }

    /**
     * @param mixed $result
     */
    protected function isAdminAjaxStillRunning($result)
    {
        return is_array($result) && isset($result['code']) && $result['code'] === 'update_in_progress_self';
    }

    /**
     * @param string $plugin
     * @param string|false $oldVersion
     * @return array
     */
    protected function adminAjaxStillRunningResponse($plugin, $oldVersion)
    {
        wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax update still running, result unknown");

        return [
            'status' => 'in_progress',
            'code' => 'update_in_progress_self',
            'message' => sprintf('An update is still running for plugin %s', dirname($plugin)),
            'data' => [
                $plugin => 'update_in_progress_self',
                $plugin . '_old_version' => $oldVersion,
            ],
        ];
    }

    protected function fingerprintMainFile($pluginFile)
    {
        $fullPath = WP_PLUGIN_DIR . '/' . $pluginFile;
        @clearstatcache(true, $fullPath);
        Opcache::invalidate($fullPath);
        if (!file_exists($fullPath)) {
            return null;
        }
        $size = @filesize($fullPath);
        $mtime = @filemtime($fullPath);
        $hash = null;
        $handle = @fopen($fullPath, 'rb');
        if ($handle) {
            $chunk = @fread($handle, 4096);
            if ($chunk !== false) {
                $hash = sha1($chunk);
            }
            @fclose($handle);
        }
        return ['mtime' => $mtime, 'size' => $size, 'hash' => $hash];
    }

    protected function fingerprintDiff($before, $after)
    {
        if ($before === null || $after === null) {
            return [
                'mtime_changed' => $before !== $after,
                'size_changed' => $before !== $after,
                'hash_changed' => $before !== $after,
            ];
        }
        return [
            'mtime_changed' => $before['mtime'] !== $after['mtime'],
            'size_changed' => $before['size'] !== $after['size'],
            'hash_changed' => $before['hash'] !== $after['hash'],
        ];
    }

    /**
     * @param string $plugin
     * @param array $delegation ['lock_token' => string, 'operation' => string] or empty
     */
    protected function sendAdminRequest($plugin, $delegation = [])
    {
        // Create nonce.
        $nonce = wp_create_nonce('wp_umbrella_update_admin_request');

        $timestamp = (string) time();
        $fields = [
            'action' => 'wp_umbrella_update_admin_request',
            'plugin' => $plugin,
            'timestamp' => $timestamp,
        ];

        if (!empty($delegation)) {
            $fields['lock_token'] = (string) $delegation['lock_token'];
            $fields['operation'] = (string) $delegation['operation'];
        }

        $signature = wp_umbrella_sign_admin_request($fields);

        $args = [
            'timeout' => 45,
            'cookies' => [],
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => array_merge($fields, [
                'nonce' => $nonce,
                'signature' => $signature,
            ]),
        ];

        $loopbackToken = wp_umbrella_issue_loopback_token(\WPUmbrella\Services\Loopback\SignedLoopback::UPDATE, $timestamp);
        if ($loopbackToken !== '') {
            $args['body'][\WPUmbrella\Services\Loopback\SignedLoopback::TOKEN_FIELD] = $loopbackToken;
        }

        // Set cookies if required.
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $name => $value) {
                $args['cookies'][] = new \WP_Http_Cookie(compact('name', 'value'));
            }
        }

        $bearer = \WPUmbrella\Core\UmbrellaRequest::createFromGlobals()->getAuthorizationBearer();
        if ($bearer) {
            $args['headers']['X-Secret-Token'] = 'Bearer ' . $bearer;
        }

        foreach (wp_umbrella_get_service('MaintenanceMode')->getLoopbackHeaders() as $headerName => $headerValue) {
            $args['headers'][$headerName] = $headerValue;
        }

        $httpAuth = isset($_SERVER['PHP_AUTH_USER']);
        if ($httpAuth) {
            $args['headers']['Authorization'] = 'Basic ' . base64_encode(
                $_SERVER['PHP_AUTH_USER'] . ':' . (isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '')
            );
        } elseif ($bearer) {
            $args['headers']['Authorization'] = 'Bearer ' . $bearer;
        }

        // Make post request.
        $url = admin_url('admin-ajax.php?force-check=1');
        wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax fallback POST to {$url} (cookies: " . count($args['cookies']) . ', bearer: ' . ($bearer ? 'yes' : 'no') . ', httpauth: ' . ($httpAuth ? 'yes' : 'no') . ')');
        $response = wp_remote_post($url, $args);

        // If request not failed.
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            wp_umbrella_debug_log("Plugin '{$plugin}' admin-ajax fallback HTTP 200");
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

    /**
     * @param string $plugin
     * @param string $installedVersion
     * @return string already_at_target|no_update_available|plugin_version_unchanged
     */
    protected function resolveUnchangedVersionCode($plugin, $installedVersion)
    {
        delete_site_transient('update_plugins');
        wp_update_plugins();
        $transient = get_site_transient('update_plugins');

        return $this->unchangedVersionCodeFromTransient($transient, $plugin, $installedVersion);
    }

    /**
     * @param mixed $transient
     * @param string $plugin
     * @param mixed $installedVersion
     * @return string already_at_target|no_update_available|plugin_version_unchanged
     */
    protected function unchangedVersionCodeFromTransient($transient, $plugin, $installedVersion)
    {
        $entry = is_object($transient) && isset($transient->response[$plugin]) ? $transient->response[$plugin] : null;
        if (!is_object($entry) || $entry instanceof \__PHP_Incomplete_Class) {
            return 'no_update_available';
        }

        $newVersion = isset($entry->new_version) ? $entry->new_version : null;
        if (is_int($newVersion) || is_float($newVersion)) {
            $newVersion = (string) $newVersion;
        }

        if (!is_string($newVersion) || $newVersion === '' || !is_string($installedVersion) || $installedVersion === '') {
            return 'no_update_available';
        }

        if (preg_match('/^\d+(\.\d+)*$/', trim($installedVersion)) !== 1 || preg_match('/^\d+(\.\d+)*$/', trim($newVersion)) !== 1) {
            return $installedVersion === $newVersion ? 'already_at_target' : 'plugin_version_unchanged';
        }

        return version_compare(trim($installedVersion), trim($newVersion), '>=') ? 'already_at_target' : 'plugin_version_unchanged';
    }
}
