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
                return [
                    'status' => 'success',
                    'code' => 'success',
                ];
            }

            if ($updated->response == 'development') {
                return [
                    'status' => 'error',
                    'code' => 'need_upgrade_manually',
                ];
            }

            $current_update = $updated;
        } else {
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

            @ob_start();
            $core = new Core_Upgrader(new UpdaterSkin());
            $result = $core->upgrade($current_update);
            @ob_end_flush();
            @ob_end_clean();

            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            if (is_wp_error($result)) {
                return [
                    'error' => $this->getError($result),
                ];

                return [
                    'status' => 'error',
                    'code' => 'unknown',
                ];
            }

            return [
                'status' => 'success',
                'code' => 'success',
            ];
        }

        return [
            'status' => 'error',
            'code' => 'unknown',
        ];
    }

    public function update()
    {
        try {
            global $wp_version, $wpdb;

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
                return [
                    'status' => 'error',
                    'code' => 'fs_unavailable',
                    'message' => 'Could not access filesystem.',
                ];
            }

            if (apply_filters('wp_umbrella_check_is_vcs_checkout', true) && $upgrader->is_vcs_checkout(ABSPATH)) {
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
                return [
                    'status' => 'error',
                    'code' => 'update_unavailable',
                    'message' => 'No WordPress core updates appear available.',
                ];
            }

            // compatiblity PHP
            $php_compat = version_compare(phpversion(), $updateData->php_version, '>=');
            if (file_exists(WP_CONTENT_DIR . '/db.php') && empty($wpdb->is_mysql)) {
                $mysql_compat = true;
            } else {
                $mysql_compat = version_compare($wpdb->db_version(), $updateData->mysql_version, '>=');
            }

            if (!$php_compat) {
                return [
                    'status' => 'error',
                    'code' => 'php_incompatible',
                    'message' => 'The new version of WordPress is incompatible with your PHP version.',
                ];
            }

            if (!$mysql_compat) {
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
                    return[
                        'status' => 'error',
                        'code' => 'previous_failure',
                        'message' => 'There was a previous failure with this update. Please update manually instead.',
                    ];
                }
            }

            $upgrader->run();

            // check populated var from hook
            if (empty($this->updateResults['core'])) {
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

                return [
                    'status' => 'error',
                    'code' => $error_code,
                    'message' => $error_msg,
                ];
            }

            wp_upgrade();
            wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

            return [
                'status' => 'success',
                'code' => 'success',
                'data' => $result
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
}
