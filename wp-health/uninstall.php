<?php

if (!defined('WP_UNINSTALL_PLUGIN')) { // If uninstall not called from WordPress exit
    exit;
}

/**
 * Each block below stands on its own: one of them failing must never skip the
 * ones behind it, or the plugin leaves its data on the site.
 *
 * Blocks are ordered by importance, so a request that runs out of time leaves
 * the least valuable cleanup unfinished rather than the most valuable one.
 */

$wpUmbrellaOptions = [
    'wp-health',
    'wphealth_version',
    'wp_health_allow_tracking',
    'wp_health_version_god_handler',
    '_wp_umbrella_is_new_hash',
    'wpumbrella_backup_path_not_allowed',
    'wp-umbrella-errors-save',
    'wp_umbrella_disallow_one_click_access',
    'wp_umbrella_backup_data_process',
    'wp_umbrella_wordpress_sizes',
    'wp_umbrella_wordpress_sizes_lock',
    'wp_umbrella_backup_suffix_security',
    'wp_umbrella_backup_version',
    'wp_umbrella_restoration_suffix_security',
    'wp_umbrella_number_trial_auto_install',
    'wp_umbrella_broken_link_checker_enabled',
    'wp_umbrella_blc_scan_interval',
    'wp_umbrella_hardening_settings',
    'wp_umbrella_hardening_htaccess_state',
    'wp_umbrella_htaccess_pending_write',
    'wp_umbrella_two_factor_key',
    'wp_umbrella_activity_log_enabled',
    'wp_umbrella_activity_log_last_sync_at',
    'wp_umbrella_activity_log_last_sync_status',
    'wp_umbrella_activity_log_sync_interval_seconds',
    'wp_umbrella_errors_buffer',
    'wp_umbrella_errors_backoff',
    'wp_umbrella_login_guard_filter_blob',
    'wp_umbrella_login_guard_filter_fetched_at',
    'wp_umbrella_login_rl_blocked',
    'wp_umbrella_last_htaccess_clean',
    'wp_umbrella_last_test_ping',
    'wp_umbrella_tls_probe',
    'wp_umbrella_transient_update_plugins',
    'wp_umbrella_transient_update_themes',
];

$wpUmbrellaTransients = [
    'wp_umbrella_auto_install_lock',
    'wp_umbrella_white_label_data_cache',
    'wp_umbrella_pairing_retry_lock',
    'wp_umbrella_snapshot_lock',
    'wp_umbrella_htaccess_block_reconcile_lock',
    'wp_umbrella_htaccess_uploads_backfill_lock',
    'wp_umbrella_login_guard_filter_backoff',
    'wp_umbrella_static_protection_probe',
    'wp_umbrella_uploads_php_probe',
    'wp_umbrella_activity_log_buffer_count',
    'wp-umbrella-error-already-send',
    'wp-umbrella-errors-save',
];

/**
 * Families written with a generated suffix, so they cannot be listed one by one.
 */
$wpUmbrellaOptionPrefixes = [
    'wp_umbrella_',
    'wp-umbrella-',
    'wpu_srn_',
];

/**
 * A transient occupies two option rows on top of the plain option row.
 */
$wpUmbrellaOptionWrappers = [
    '',
    '_transient_',
    '_transient_timeout_',
];

$wpUmbrellaTables = [
    'umbrella_log',
    'umbrella_task',
    'umbrella_backup',
    'umbrella_task_backup',
    'umbrella_collected_links',
    'umbrella_activity_log_buffer',
    'umbrella_redirects',
];

$wpUmbrellaCronHooks = [
    'wp_umbrella_snapshot_data_run_queue',
    'wp_umbrella_error_check_run_queue',
    'wp_umbrella_clean_table_run_queue',
    'wp_umbrella_task_backup_run_queue',
    'wp_umbrella_run_manual_backup_task',
    'wp_umbrella_stop_manual_backup_task',
];

$wpUmbrellaScheduledActions = [
    ['wp_umbrella_send_errors', 'umbrella_errors'],
    ['wp_umbrella_wordpress_sizes_scan', 'umbrella_sizes'],
    ['wp_umbrella_tls_probe', 'umbrella_tls'],
    ['wp_umbrella_activity_log_sync', 'wp-umbrella'],
    ['wp_umbrella_snapshot_data', ''],
];

$wpUmbrellaUserMeta = [
    'wp_umbrella_2fa_secret',
    'wp_umbrella_2fa_enrolled_at',
    'wp_umbrella_2fa_recovery_code',
    'wp_umbrella_2fa_last_counter',
    'wp_umbrella_suspended',
];

$wpUmbrellaMuPlugins = [
    'InitUmbrella.php',
    '_WPHealthHandlerMU.php',
];

$wpUmbrellaUploadFiles = [
    'two-factor-key.php',
    'login-guard-filter.bin',
    'login-guard-filter.bin.tmp',
];

$wpUmbrellaUploadGuard = 'index.php';

$wpUmbrellaUploadGuardContents = '<?php // Silence is golden.';

$wpUmbrellaOptionSweepLimit = 5000;

$wpUmbrellaOptionSweepChunk = 500;

/**
 * A network can hold far more sites than one PHP request can walk. The cleanup
 * is capped on both counts and always starts with the site running it.
 */
$wpUmbrellaSiteLimit = 500;

$wpUmbrellaSiteBatch = 100;

/**
 * The budget is half of the execution time still available, so the same
 * arithmetic holds from a 10 second limit to a generous one. Several plugins
 * can be deleted in a single request, so the time already spent counts against
 * it. A limit of 0 means no limit and gets no deadline at all.
 *
 * The elapsed time is wall clock, while the limit it is measured against is
 * not: PHP stops counting while a query or a stream operation runs. A request
 * older than the limit is still executing, which makes it proof that the two
 * clocks disagree rather than proof that nothing is left, so the budget never
 * drops below $wpUmbrellaMinimumBudget. Every block commits on its own, so
 * overrunning costs at most the work that stopping early would have skipped.
 *
 * $wpUmbrellaDeadline is a timestamp, or 0 for "run to completion".
 */
$wpUmbrellaMinimumBudget = 5;

$wpUmbrellaNow = time();

$wpUmbrellaTimeLimit = (int) ini_get('max_execution_time');

$wpUmbrellaDeadline = 0;

if ($wpUmbrellaTimeLimit > 0) {
    $wpUmbrellaElapsed = 0;

    if (isset($_SERVER['REQUEST_TIME']) && is_numeric($_SERVER['REQUEST_TIME'])) {
        $wpUmbrellaElapsed = $wpUmbrellaNow - (int) $_SERVER['REQUEST_TIME'];
    }

    if ($wpUmbrellaElapsed < 0 || $wpUmbrellaElapsed > $wpUmbrellaTimeLimit) {
        $wpUmbrellaElapsed = $wpUmbrellaTimeLimit;
    }

    $wpUmbrellaBudget = intdiv($wpUmbrellaTimeLimit - $wpUmbrellaElapsed, 2);

    if ($wpUmbrellaBudget < $wpUmbrellaMinimumBudget) {
        $wpUmbrellaBudget = $wpUmbrellaMinimumBudget;
    }

    $wpUmbrellaDeadline = $wpUmbrellaNow + $wpUmbrellaBudget;
}

try {
    if (!class_exists('WPUmbrella\Services\Security\HtaccessFile')
        && file_exists(__DIR__ . '/src/Services/Security/HtaccessFile.php')) {
        require_once __DIR__ . '/src/Services/Security/HtaccessFile.php';
    }
} catch (\Throwable $e) {
    error_log('WP Umbrella uninstall, htaccess loading: ' . $e->getMessage());
}

try {
    if (defined('WPMU_PLUGIN_DIR')) {
        foreach ($wpUmbrellaMuPlugins as $wpUmbrellaMuPlugin) {
            $wpUmbrellaMuPath = WPMU_PLUGIN_DIR . '/' . $wpUmbrellaMuPlugin;

            if (file_exists($wpUmbrellaMuPath)) {
                @unlink($wpUmbrellaMuPath);
            }
        }
    }
} catch (\Throwable $e) {
    error_log('WP Umbrella uninstall, mu-plugins: ' . $e->getMessage());
}

try {
    global $wpdb;

    $wpUmbrellaPlaceholders = implode(', ', array_fill(0, count($wpUmbrellaUserMeta), '%s'));

    $wpdb->query(
        call_user_func_array(
            [$wpdb, 'prepare'],
            array_merge(
                ["DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ({$wpUmbrellaPlaceholders})"],
                $wpUmbrellaUserMeta
            )
        )
    );
} catch (\Throwable $e) {
    error_log('WP Umbrella uninstall, user meta: ' . $e->getMessage());
}

try {
    if (is_multisite()) {
        delete_site_option('wp_umbrella_hardening_require_2fa_admin');
        delete_site_option('wp_umbrella_hardening_htaccess_umbrella_block');
    }
} catch (\Throwable $e) {
    error_log('WP Umbrella uninstall, network options: ' . $e->getMessage());
}

$wpUmbrellaSiteIds = [0];

$wpUmbrellaCurrentBlog = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;

try {
    if (is_multisite() && function_exists('get_sites')) {
        $wpUmbrellaOffset = 0;

        while (count($wpUmbrellaSiteIds) <= $wpUmbrellaSiteLimit) {
            $wpUmbrellaBatch = get_sites([
                'fields' => 'ids',
                'number' => $wpUmbrellaSiteBatch,
                'offset' => $wpUmbrellaOffset,
                'orderby' => 'id',
            ]);

            if (empty($wpUmbrellaBatch)) {
                break;
            }

            foreach ($wpUmbrellaBatch as $wpUmbrellaBatchId) {
                if ((int) $wpUmbrellaBatchId === $wpUmbrellaCurrentBlog) {
                    continue;
                }

                $wpUmbrellaSiteIds[] = (int) $wpUmbrellaBatchId;
            }

            $wpUmbrellaOffset += $wpUmbrellaSiteBatch;
        }

        $wpUmbrellaSiteIds = array_slice($wpUmbrellaSiteIds, 0, $wpUmbrellaSiteLimit + 1);
    }
} catch (\Throwable $e) {
    error_log('WP Umbrella uninstall, site list: ' . $e->getMessage());
}

/**
 * The deadline can only stop the walk once a site other than the current one
 * has been cleaned, the way the option sweep always lands its first chunk.
 */
$wpUmbrellaWalkedSites = 0;

foreach ($wpUmbrellaSiteIds as $wpUmbrellaSiteId) {
    $wpUmbrellaSwitched = false;

    if ($wpUmbrellaSiteId > 0) {
        if ($wpUmbrellaWalkedSites > 0 && $wpUmbrellaDeadline > 0 && time() >= $wpUmbrellaDeadline) {
            error_log('WP Umbrella uninstall: time budget reached, remaining sites left untouched');
            break;
        }

        if (!function_exists('switch_to_blog')) {
            break;
        }

        switch_to_blog($wpUmbrellaSiteId);
        $wpUmbrellaSwitched = true;
        $wpUmbrellaWalkedSites++;
    }

    try {
        if (class_exists('WPUmbrella\Services\Security\HtaccessFile')) {
            (new WPUmbrella\Services\Security\HtaccessFile())->cleanUmbrellaBlockFiles();
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, htaccess: ' . $e->getMessage());
    }

    try {
        $wpUmbrellaUploads = wp_upload_dir(null, false);

        if (is_array($wpUmbrellaUploads) && !empty($wpUmbrellaUploads['basedir'])) {
            $wpUmbrellaDir = rtrim($wpUmbrellaUploads['basedir'], '/\\') . '/wp-umbrella';

            foreach ($wpUmbrellaUploadFiles as $wpUmbrellaUploadFile) {
                $wpUmbrellaPath = $wpUmbrellaDir . '/' . $wpUmbrellaUploadFile;

                if (file_exists($wpUmbrellaPath)) {
                    @unlink($wpUmbrellaPath);
                }
            }

            $wpUmbrellaLeftOver = @scandir($wpUmbrellaDir);

            if (is_array($wpUmbrellaLeftOver)) {
                $wpUmbrellaLeftOver = array_diff($wpUmbrellaLeftOver, ['.', '..', $wpUmbrellaUploadGuard]);
                $wpUmbrellaGuardPath = $wpUmbrellaDir . '/' . $wpUmbrellaUploadGuard;

                if (empty($wpUmbrellaLeftOver)) {
                    if (file_exists($wpUmbrellaGuardPath)) {
                        @unlink($wpUmbrellaGuardPath);
                    }

                    if (!@rmdir($wpUmbrellaDir) && is_dir($wpUmbrellaDir)) {
                        @file_put_contents($wpUmbrellaGuardPath, $wpUmbrellaUploadGuardContents);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, uploads: ' . $e->getMessage());
    }

    try {
        foreach ($wpUmbrellaOptions as $wpUmbrellaOption) {
            delete_option($wpUmbrellaOption);
        }

        foreach ($wpUmbrellaTransients as $wpUmbrellaTransient) {
            delete_transient($wpUmbrellaTransient);
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, options: ' . $e->getMessage());
    }

    try {
        foreach ($wpUmbrellaCronHooks as $wpUmbrellaCronHook) {
            wp_clear_scheduled_hook($wpUmbrellaCronHook);
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, scheduled hooks: ' . $e->getMessage());
    }

    try {
        if (function_exists('as_unschedule_all_actions')) {
            foreach ($wpUmbrellaScheduledActions as $wpUmbrellaScheduledAction) {
                as_unschedule_all_actions($wpUmbrellaScheduledAction[0], [], $wpUmbrellaScheduledAction[1]);
            }
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, scheduled actions: ' . $e->getMessage());
    }

    try {
        global $wpdb;

        foreach ($wpUmbrellaTables as $wpUmbrellaTable) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$wpUmbrellaTable}");
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, database: ' . $e->getMessage());
    }

    try {
        global $wpdb;

        $wpUmbrellaLikes = [];
        $wpUmbrellaLikeValues = [];

        foreach ($wpUmbrellaOptionPrefixes as $wpUmbrellaPrefix) {
            foreach ($wpUmbrellaOptionWrappers as $wpUmbrellaWrapper) {
                $wpUmbrellaLikes[] = 'option_name LIKE %s';
                $wpUmbrellaLikeValues[] = $wpdb->esc_like($wpUmbrellaWrapper . $wpUmbrellaPrefix) . '%';
            }
        }

        $wpUmbrellaSelect = "SELECT option_name FROM {$wpdb->options} WHERE "
            . implode(' OR ', $wpUmbrellaLikes)
            . ' LIMIT %d';

        $wpUmbrellaSelectArgs = array_merge(
            [$wpUmbrellaSelect],
            $wpUmbrellaLikeValues,
            [$wpUmbrellaOptionSweepChunk]
        );

        $wpUmbrellaSwept = 0;

        while ($wpUmbrellaSwept < $wpUmbrellaOptionSweepLimit) {
            $wpUmbrellaNames = (array) $wpdb->get_col(
                call_user_func_array([$wpdb, 'prepare'], $wpUmbrellaSelectArgs)
            );

            if (empty($wpUmbrellaNames)) {
                break;
            }

            $wpUmbrellaPlaceholders = implode(', ', array_fill(0, count($wpUmbrellaNames), '%s'));

            $wpdb->query(
                call_user_func_array(
                    [$wpdb, 'prepare'],
                    array_merge(
                        ["DELETE FROM {$wpdb->options} WHERE option_name IN ({$wpUmbrellaPlaceholders})"],
                        array_values($wpUmbrellaNames)
                    )
                )
            );

            foreach ($wpUmbrellaNames as $wpUmbrellaName) {
                wp_cache_delete($wpUmbrellaName, 'options');

                if (strpos($wpUmbrellaName, '_transient_timeout_') === 0) {
                    continue;
                }

                if (strpos($wpUmbrellaName, '_transient_') === 0) {
                    wp_cache_delete(substr($wpUmbrellaName, strlen('_transient_')), 'transient');
                }
            }

            wp_cache_delete('alloptions', 'options');

            $wpUmbrellaSwept += count($wpUmbrellaNames);

            if (count($wpUmbrellaNames) < $wpUmbrellaOptionSweepChunk) {
                break;
            }

            if ($wpUmbrellaDeadline > 0 && time() >= $wpUmbrellaDeadline) {
                error_log('WP Umbrella uninstall: time budget reached, option sweep left unfinished');
                break;
            }
        }
    } catch (\Throwable $e) {
        error_log('WP Umbrella uninstall, option sweep: ' . $e->getMessage());
    }

    if ($wpUmbrellaSwitched && function_exists('restore_current_blog')) {
        restore_current_blog();
    }
}
