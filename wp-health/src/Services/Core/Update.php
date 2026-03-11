<?php
namespace WPUmbrella\Services\Core;

use WPUmbrella\Core\Update\Plugin\UpdaterSkin;
use WPUmbrella\Services\Manage\BaseManageUpdate;
use Automatic_Upgrader_Skin;
use Exception;
use WP_Automatic_Updater;
use Core_Upgrader;
use WP_Error;

class Update extends BaseManageUpdate
{
    const NAME_SERVICE = 'CoreUpdate';

    protected $updateResults = null;

    public function captureResults($results)
    {
        $this->updateResults = $results;
    }

    public function upgradeByCoreUpgrader()
    {
        wp_umbrella_debug_log("Core update (Core_Upgrader) started");

        @ob_start();

        if (file_exists(ABSPATH . '/wp-admin/includes/update.php')) {
            include_once ABSPATH . '/wp-admin/includes/update.php';
        }

        $current_update = false;
        @ob_end_flush();
        @ob_end_clean();
        $core = wp_umbrella_get_service('WordPressContext')->getTransient('update_core');

        if (isset($core->updates) && !empty($core->updates)) {
            $updates = $core->updates[0];
            $updated = $core->updates[0];
            if (!isset($updated->response) || $updated->response == 'latest') {
                wp_umbrella_debug_log("Core update: already at latest version");
                return [
                    'status' => 'success',
                    'code' => 'success',
                ];
            }

            if ($updated->response == 'development') {
                wp_umbrella_debug_log("Core update: development version, needs manual upgrade");
                return [
                    'status' => 'error',
                    'code' => 'need_upgrade_manually',
                ];
            }

            $current_update = $updated;
        } else {
            wp_umbrella_debug_log("Core update: no update transient found");
            return [
                'status' => 'error',
                'code' => 'refresh_transient_failed',
            ];
        }

        if ($current_update != false) {
            global $wp_filesystem, $wp_version;

            if (!class_exists('Core_Upgrader')) {
                include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            }

            wp_umbrella_debug_log("Core update: upgrading from {$wp_version} to {$current_update->current}");

            @ob_start();
            $core = new Core_Upgrader(new UpdaterSkin());
            $result = $core->upgrade($current_update);
            @ob_end_flush();
            @ob_end_clean();

            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            if (is_wp_error($result)) {
                wp_umbrella_debug_log("Core update error: " . $result->get_error_code() . ' - ' . $result->get_error_message());
                return [
                    'error' => $this->getError($result),
                ];

                return [
                    'status' => 'error',
                    'code' => 'unknown',
                ];
            }

            wp_umbrella_debug_log("Core update (Core_Upgrader) completed successfully");
            return [
                'status' => 'success',
                'code' => 'success',
            ];
        }

        wp_umbrella_debug_log("Core update: no current update available");
        return [
            'status' => 'error',
            'code' => 'unknown',
        ];
    }

    public function update()
    {
        try {
            global $wp_version, $wpdb;

            wp_umbrella_debug_log("Core update (WP_Automatic_Updater) started from version {$wp_version}");

            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            include_once ABSPATH . 'wp-admin/includes/admin.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            add_action('automatic_updates_complete', [$this, 'captureResults']);

            add_filter('auto_update_core', '__return_true', 99999); // temporarily allow core autoupdates
            add_filter('allow_major_auto_core_updates', '__return_true', 99999); // temporarily allow core autoupdates
            add_filter('allow_minor_auto_core_updates', '__return_true', 99999); // temporarily allow core autoupdates
            add_filter('auto_core_update_send_email', '__return_false', 99999);
            add_filter('auto_update_core', '__return_true', 99999); // temporarily allow core autoupdates
            add_filter('auto_update_theme', '__return_false', 99999);
            add_filter('auto_update_plugin', '__return_false', 99999);

            $upgrader = new WP_Automatic_Updater();

            // Used to see if WP_Filesystem is set up to allow unattended updates.
            $skin = new Automatic_Upgrader_Skin();

            if (!$skin->request_filesystem_credentials(false, ABSPATH, false)) {
                wp_umbrella_debug_log("Core update: filesystem credentials unavailable");
                return [
                    'status' => 'error',
                    'code' => 'fs_unavailable',
                    'message' => 'Could not access filesystem.',
                ];
            }

            if (apply_filters('wp_umbrella_check_is_vcs_checkout', true) && $upgrader->is_vcs_checkout(ABSPATH)) {
                wp_umbrella_debug_log("Core update: VCS checkout detected, aborting");
                return [
                    'status' => 'error',
                    'code' => 'is_vcs_checkout',
                    'message' => 'Automatic core updates are disabled when WordPress is checked out from version control.',
                ];
            }

            $updates = wp_umbrella_get_service('WordPressContext')->getTransient('update_core');

            if (!$updates || empty($updates->updates)) {
                return [
                    'status' => 'error',
                    'code' => 'no_updates',
                    'message' => '',
                ];
            }

            $updateData = false;

            foreach ($updates->updates as $update) {
                if ('upgrade' != $update->response) {
                    continue;
                }

                if (!$updateData || version_compare($update->current, $updateData->current, '>')) {
                    $updateData = $update;
                }
            }

            if (!$updateData) {
                wp_umbrella_debug_log("Core update: no upgrade-type update available");
                return [
                    'status' => 'error',
                    'code' => 'update_unavailable',
                    'message' => 'No WordPress core updates appear available.',
                ];
            }

            wp_umbrella_debug_log("Core update: target version {$updateData->current} (PHP >= {$updateData->php_version}, MySQL >= {$updateData->mysql_version})");

            // compatiblity PHP
            $php_compat = version_compare(phpversion(), $updateData->php_version, '>=');
            if (file_exists(WP_CONTENT_DIR . '/db.php') && empty($wpdb->is_mysql)) {
                $mysql_compat = true;
            } else {
                $mysql_compat = version_compare($wpdb->db_version(), $updateData->mysql_version, '>=');
            }

            if (!$php_compat) {
                wp_umbrella_debug_log("Core update: PHP version " . phpversion() . " incompatible with required {$updateData->php_version}");
                return [
                    'status' => 'error',
                    'code' => 'php_incompatible',
                    'message' => 'The new version of WordPress is incompatible with your PHP version.',
                ];
            }

            if (!$mysql_compat) {
                wp_umbrella_debug_log("Core update: MySQL version incompatible with required {$updateData->mysql_version}");
                return[
                    'status' => 'error',
                    'code' => 'mysql_incompatible',
                    'message' => 'The new version of WordPress is incompatible with your MySQL version.',
                ];
            }

            // If this was a critical update failure last try, cannot update.
            $skip = false;
            $failure_data = get_site_option('auto_core_update_failed');
            if ($failure_data) {
                if (!empty($failure_data['critical'])) {
                    $skip = true;
                }

                // Don't claim we can update on update-core.php if we have a non-critical failure logged.
                if ($wp_version == $failure_data['current'] && false !== strpos($updateData->current, '.1.next.minor')) {
                    $skip = true;
                }

                // Cannot update if we're retrying the same A to B update that caused a non-critical failure.
                // Some non-critical failures do allow retries, like download_failed.
                if (empty($failure_data['retry']) && $wp_version == $failure_data['current'] && $updateData->current == $failure_data['attempted']) {
                    $skip = true;
                }

                if ($skip) {
                    wp_umbrella_debug_log("Core update: skipped due to previous failure (critical: " . (!empty($failure_data['critical']) ? 'true' : 'false') . ")");
                    return[
                        'status' => 'error',
                        'code' => 'previous_failure',
                        'message' => 'There was a previous failure with this update. Please update manually instead.',
                    ];
                }
            }

            wp_umbrella_debug_log("Core update: running WP_Automatic_Updater...");
            $upgrader->run();

            // check populated var from hook
            if (empty($this->updateResults['core'])) {
                wp_umbrella_debug_log("Core update: no results captured from automatic_updates_complete hook");
                return [
                    'status' => 'error',
                    'code' => 'unknown_update',
                    'message' => 'Update failed for an unknown reason.',
                ];
            }

            $update_result = $this->updateResults['core'][0];

            $result = $update_result->result;

            if (is_wp_error($result)) {
                $error_code = $result->get_error_code();
                $error_msg = $result->get_error_message();

                // if a rollback was run and errored append that to message.
                if ($error_code === 'rollback_was_required' && is_wp_error($result->get_error_data()->rollback)) {
                    $rollback_result = $result->get_error_data()->rollback;
                    $error_msg .= ' Rollback: ' . $rollback_result->get_error_message();
                }

                wp_umbrella_debug_log("Core update error: {$error_code} - {$error_msg}");
                return [
                    'status' => 'error',
                    'code' => $error_code,
                    'message' => $error_msg,
                ];
            }

            wp_upgrade();
            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            wp_umbrella_debug_log("Core update (WP_Automatic_Updater) completed successfully to version {$result}");

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => $result
            ];
        } catch (\Exception $e) {
            wp_umbrella_debug_log("Core update exception: " . $e->getMessage());
            $data['message'] = $e->getMessage();

            return [
                'status' => 'error',
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
                'data' => ''
            ];
        }
    }
}
