<?php

if (!defined('WP_UNINSTALL_PLUGIN')) { // If uninstall not called from WordPress exit
    exit;
}

try {
    delete_option('wp_health_allow_tracking');
    delete_option('wp_umbrella_disallow_one_click_access');
    delete_option('wp-health');
    delete_option('wphealth_version');
    delete_option('wp_umbrella_backup_data_process');
    delete_option('wp_umbrella_backup_suffix_security');
    delete_option('wp_umbrella_number_trial_auto_install');
    delete_transient('wp_umbrella_white_label_data_cache');

    global $wpdb;

    // Delete custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_log");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_task");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_backup");

    wp_clear_scheduled_hook('wp_umbrella_snapshot_data_run_queue');

    // Backup scheduelr
    wp_clear_scheduled_hook('wp_umbrella_error_check_run_queue');
    wp_clear_scheduled_hook('wp_umbrella_clean_table_run_queue');
    wp_clear_scheduled_hook('wp_umbrella_task_backup_run_queue');
    wp_clear_scheduled_hook('wp_umbrella_run_manual_backup_task');
    wp_clear_scheduled_hook('wp_umbrella_stop_manual_backup_task');
} catch (\Exception $e) {
}
