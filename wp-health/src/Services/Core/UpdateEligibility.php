<?php
namespace WPUmbrella\Services\Core;

/**
 * What stands between this site and the core upgrade it was asked to apply.
 *
 * Each check answers with the response to return to the caller, or null when
 * it has nothing to object to.
 */
class UpdateEligibility
{
    const NAME_SERVICE = 'CoreUpdateEligibility';

    /**
     * Whether WordPress can write to its own files unattended.
     */
    public function filesystem($upgrader, $skin)
    {
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

        return null;
    }

    /**
     * Whether the server can run the version being offered.
     */
    public function server($updateData)
    {
        global $wpdb;

        if (!version_compare(phpversion(), $updateData->php_version, '>=')) {
            wp_umbrella_debug_log(
                "Core update: PHP version " . phpversion() . " incompatible with required {$updateData->php_version}"
            );

            return [
                'status' => 'error',
                'code' => 'php_incompatible',
                'message' => 'The new version of WordPress is incompatible with your PHP version.',
            ];
        }

        $usesDropIn = file_exists(WP_CONTENT_DIR . '/db.php') && empty($wpdb->is_mysql);

        if (!$usesDropIn && !version_compare($wpdb->db_version(), $updateData->mysql_version, '>=')) {
            wp_umbrella_debug_log("Core update: MySQL version incompatible with required {$updateData->mysql_version}");

            return [
                'status' => 'error',
                'code' => 'mysql_incompatible',
                'message' => 'The new version of WordPress is incompatible with your MySQL version.',
            ];
        }

        return null;
    }

    /**
     * Whether the last attempt at this same upgrade left something behind that
     * makes retrying pointless or unsafe. Mirrors what update-core.php does.
     */
    public function previousFailure($updateData)
    {
        global $wp_version;

        $failure_data = get_site_option('auto_core_update_failed');

        if (!$failure_data) {
            return null;
        }

        $skip = !empty($failure_data['critical']);

        // Don't claim we can update on update-core.php if we have a non-critical failure logged.
        if ($wp_version == $failure_data['current'] && false !== strpos($updateData->current, '.1.next.minor')) {
            $skip = true;
        }

        // Cannot update if we're retrying the same A to B update that caused a non-critical failure.
        // Some non-critical failures do allow retries, like download_failed.
        if (
            empty($failure_data['retry'])
            && $wp_version == $failure_data['current']
            && $updateData->current == $failure_data['attempted']
        ) {
            $skip = true;
        }

        if (!$skip) {
            return null;
        }

        wp_umbrella_debug_log(
            "Core update: skipped due to previous failure (critical: "
            . (!empty($failure_data['critical']) ? 'true' : 'false') . ")"
        );

        return [
            'status' => 'error',
            'code' => 'previous_failure',
            'message' => 'There was a previous failure with this update. Please update manually instead.',
        ];
    }
}
