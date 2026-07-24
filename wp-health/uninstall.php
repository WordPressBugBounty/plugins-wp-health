<?php

if (!defined('WP_UNINSTALL_PLUGIN')) { // If uninstall not called from WordPress exit
    exit;
}

try {
    if (!class_exists('WPUmbrella\Services\Security\HtaccessFile')
        && file_exists(__DIR__ . '/src/Services/Security/HtaccessFile.php')) {
        require_once __DIR__ . '/src/Services/Security/HtaccessFile.php';
    }
    if (class_exists('WPUmbrella\Services\Security\HtaccessFile')) {
        (new WPUmbrella\Services\Security\HtaccessFile())->cleanUmbrellaBlock();
    }

    delete_option('wp_health_allow_tracking');
    delete_option('wp_umbrella_disallow_one_click_access');
    delete_option('wp-health');
    delete_option('wphealth_version');
    delete_option('wp_umbrella_backup_data_process');
    delete_option('wp_umbrella_backup_suffix_security');
    delete_option('wp_umbrella_number_trial_auto_install');
    delete_transient('wp_umbrella_auto_install_lock');
    delete_transient('wp_umbrella_white_label_data_cache');

    global $wpdb;

    // Delete custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_log");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_task");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_backup");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_task_backup");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_collected_links");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_activity_log_buffer");

    // Broken Link Checker options
    delete_option('wp_umbrella_broken_link_checker_enabled');
    delete_option('wp_umbrella_blc_scan_interval');

    delete_option('wp_umbrella_hardening_settings');
    delete_option('wp_umbrella_htaccess_pending_write');

    wp_clear_scheduled_hook('wp_umbrella_snapshot_data_run_queue');

    // Backup scheduelr
    wp_clear_scheduled_hook('wp_umbrella_error_check_run_queue');
    wp_clear_scheduled_hook('wp_umbrella_clean_table_run_queue');
    wp_clear_scheduled_hook('wp_umbrella_task_backup_run_queue');
    wp_clear_scheduled_hook('wp_umbrella_run_manual_backup_task');
    wp_clear_scheduled_hook('wp_umbrella_stop_manual_backup_task');
} catch (\Exception $e) {
}
