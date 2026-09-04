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

    /**
     * Reported rather than answered with a success. WordPress offering nothing
     * to upgrade to is not the same as having upgraded, and saying otherwise is
     * what let a site sit on an old version while the dashboard showed it as up
     * to date.
     */
    protected function nothingToApply($offers)
    {
        $responses = $offers->responses();

        if (empty($responses)) {
            wp_umbrella_debug_log("Core update: no update transient found");

            return [
                'status' => 'error',
                'code' => 'refresh_transient_failed',
            ];
        }

        if (in_array('development', $responses, true)) {
            wp_umbrella_debug_log("Core update: development version, needs manual upgrade");

            return [
                'status' => 'error',
                'code' => 'need_upgrade_manually',
            ];
        }

        wp_umbrella_debug_log(
            'Core update: no upgrade offer available (responses: ' . implode(', ', $responses) . ')'
        );

        return [
            'status' => 'error',
            'code' => 'update_unavailable',
        ];
    }

    public function upgradeByCoreUpgrader()
    {
        wp_umbrella_debug_log("Core update (Core_Upgrader) started");

        @ob_start();

        if (file_exists(ABSPATH . '/wp-admin/includes/update.php')) {
            include_once ABSPATH . '/wp-admin/includes/update.php';
        }

        @ob_end_flush();
        @ob_end_clean();

        $offers = new UpdateOffers();
        $current_update = $offers->resolve();

        if ($current_update === null) {
            return $this->nothingToApply($offers);
        }

        global $wp_filesystem, $wp_version;

        if (!class_exists('Core_Upgrader')) {
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        wp_umbrella_debug_log("Core update: upgrading from {$wp_version} to {$current_update->current}");

        @ob_start();
        $upgrader = new Core_Upgrader(new UpdaterSkin());
        $upgradeContext = wp_umbrella_get_service('UpgradeContext');
        $upgradeContext->begin();

        try {
            $result = $upgrader->upgrade($current_update);
        } finally {
            $upgradeContext->end();
        }
        @ob_end_flush();
        @ob_end_clean();

        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

        if (is_wp_error($result)) {
            $errorCode = $result->get_error_code();

            wp_umbrella_debug_log("Core update error: " . $errorCode . ' - ' . $result->get_error_message());

            return [
                'status' => 'error',
                'code' => !empty($errorCode) ? $errorCode : 'unknown',
                'message' => $result->get_error_message(),
                'error' => $this->getError($result),
            ];
        }

        wp_umbrella_debug_log("Core update (Core_Upgrader) completed successfully");
        return [
            'status' => 'success',
            'code' => 'success',
        ];
    }

    public function update()
    {
        try {
            global $wp_version;

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
            $eligibility = new UpdateEligibility();

            if ($blocked = $eligibility->filesystem($upgrader, $skin)) {
                return $blocked;
            }

            $offers = new UpdateOffers();
            $updateData = $offers->resolve();

            if ($updateData === null) {
                if (!$offers->responses()) {
                    return [
                        'status' => 'error',
                        'code' => 'no_updates',
                        'message' => '',
                    ];
                }

                wp_umbrella_debug_log("Core update: no upgrade-type update available");
                return [
                    'status' => 'error',
                    'code' => 'update_unavailable',
                    'message' => 'No WordPress core updates appear available.',
                ];
            }

            wp_umbrella_debug_log("Core update: target version {$updateData->current} (PHP >= {$updateData->php_version}, MySQL >= {$updateData->mysql_version})");

            if ($blocked = $eligibility->server($updateData)) {
                return $blocked;
            }

            if ($blocked = $eligibility->previousFailure($updateData)) {
                return $blocked;
            }

            wp_umbrella_debug_log("Core update: running WP_Automatic_Updater...");
            $upgradeContext = wp_umbrella_get_service('UpgradeContext');
            $upgradeContext->begin();

            try {
                $upgrader->run();
            } finally {
                $upgradeContext->end();
            }

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
