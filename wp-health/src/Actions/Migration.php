<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;

class Migration implements ExecuteHooks
{
    public function hooks()
    {
        add_action('admin_init', [$this, 'upgrader']);
    }

    protected function updateMuPlugin()
    {
        if (!is_writable(dirname(WPMU_PLUGIN_DIR))) {
            return false;
        }

        if (!file_exists(WPMU_PLUGIN_DIR)) {
            wp_mkdir_p(WPMU_PLUGIN_DIR);
        }

        try {
            if (!@copy(
                WP_UMBRELLA_DIR . '/src/Core/MuPlugins/InitUmbrella.php',
                WPMU_PLUGIN_DIR . '/InitUmbrella.php'
            )) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function upgrader()
    {
        $currentVersion = get_option('wphealth_version');

        if (version_compare($currentVersion, WP_UMBRELLA_VERSION, '<')) {
            update_option('wphealth_version', WP_UMBRELLA_VERSION, false);
        }

        // Migrate project with secret token
        if ($currentVersion && version_compare($currentVersion, '2.7.0', '<')) {
            $apiKey = wp_umbrella_get_api_key();

            $secretToken = wp_umbrella_generate_random_string(128);
            $options = wp_umbrella_get_service('Option')->getOptions([
                'secure' => false
            ]);

            $options['secret_token'] = $secretToken;

            wp_umbrella_get_service('Option')->setOptions($options);

            $responseValidateSecret = wp_umbrella_get_service('Projects')->validateSecretToken([
                'base_url' => site_url(),
                'rest_url' => rest_url(),
                'secret_token' => $secretToken
            ], $apiKey);

            if (!is_array($responseValidateSecret) || !isset($responseValidateSecret['success'])) {
                unset($options['secret_token']);
                wp_umbrella_get_service('Option')->setOptions($options);
                return;
            }

            if (!$responseValidateSecret['success']) {
                return;
            }

            $options = wp_umbrella_get_service('Option')->getOptions([
                'secure' => false
            ]);

            $options['secret_token'] = $secretToken;

            wp_umbrella_get_service('Option')->setOptions($options);
        }

        // Migrate project with hash tokens
        if ($currentVersion && version_compare($currentVersion, '2.11.0', '<')) {
            $secretToken = wp_umbrella_get_secret_token();

            $options = wp_umbrella_get_service('Option')->getOptions([
                'secure' => false
            ]);

            $options['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($options['secret_token']);

            wp_umbrella_get_service('Option')->setOptions($options);
        }

        if ($currentVersion && version_compare($currentVersion, '2.15.4', '<')) {
            add_rewrite_endpoint('umbrella-backup', EP_ROOT);
            add_rewrite_endpoint('umbrella-restore', EP_ROOT);

            flush_rewrite_rules();
        }

        if ($currentVersion && version_compare($currentVersion, '2.21.0', '<')) {
            $this->updateMuPlugin();
        }

        if ($currentVersion && version_compare($currentVersion, '2.22.0', '<')) {
            wp_clear_scheduled_hook('wp_umbrella_task_backup_run_queue');
            wp_clear_scheduled_hook('wp_umbrella_clean_table_run_queue');
            wp_clear_scheduled_hook('wp_umbrella_error_check_run_queue');
            wp_clear_scheduled_hook('wp_umbrella_snapshot_data_run_queue');

            global $wpdb;

            // Delete custom tables
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_log");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_task");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_backup");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_task_backup");
        }

        if ($currentVersion && version_compare($currentVersion, '2.22.1', '<')) {
            \WPUmbrella\Services\BrokenLinkChecker\RedirectTableManager::createTable();

            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}umbrella_collected_links");
            delete_option('wp_umbrella_broken_link_checker_enabled');
            delete_option('wp_umbrella_blc_scan_interval');
        }
    }
}
