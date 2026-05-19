<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Core\Hooks\DeactivationHook;

class Option implements ExecuteHooksBackend, ActivationHook, DeactivationHook
{
    const SECURED_VALUE = 'XXXXXX';

    protected $optionService;

    public function __construct()
    {
        $this->optionService = wp_umbrella_get_service('Option');
    }

    public function hooks()
    {
        add_action('admin_init', [$this, 'init']);
        add_action('admin_post_wp_umbrella_support_option', [$this, 'supportOption']);
        add_action('admin_post_wp_umbrella_regenerate_secret_token', [$this, 'regenerateSecretToken']);
        add_action('wp_ajax_wp_umbrella_repair_ajax', [$this, 'repairAjax']);
    }

    public function deactivate()
    {
        delete_option('wp_umbrella_backup_data_process');
        delete_transient('wp_umbrella_white_label_data_cache');
    }

    public function activate()
    {
        update_option('wphealth_version', WP_UMBRELLA_VERSION, false);
        $options = $this->optionService->getOptions([
            'secure' => false,
        ]);

        $this->optionService->setOptions($options);
    }

    public function supportOption()
    {
        if (!isset($_POST['_wpnonce'])) {
            wp_redirect(admin_url());
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_redirect(admin_url());
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_support_option')) {
            wp_redirect(admin_url());
            return;
        }

        if (isset($_POST['wp_health_allow_tracking']) && $_POST['wp_health_allow_tracking'] === '1') {
            update_option('wp_health_allow_tracking', true);
        } else {
            update_option('wp_health_allow_tracking', false);
        }

        if (isset($_POST['wp_umbrella_disallow_one_click_access']) && $_POST['wp_umbrella_disallow_one_click_access'] === '1') {
            delete_option('wp_umbrella_disallow_one_click_access');
        } else {
            update_option('wp_umbrella_disallow_one_click_access', true);
        }

        $previousIntervalSeconds = SyncScheduler::resolveInterval();

        if (isset($_POST['wp_umbrella_activity_log_sync_interval_minutes'])) {
            $minutes = (int) $_POST['wp_umbrella_activity_log_sync_interval_minutes'];
            $seconds = $minutes * 60;

            if ($seconds < SyncScheduler::MIN_INTERVAL_SECONDS) {
                $seconds = SyncScheduler::MIN_INTERVAL_SECONDS;
            }

            if ($seconds > SyncScheduler::MAX_INTERVAL_SECONDS) {
                $seconds = SyncScheduler::MAX_INTERVAL_SECONDS;
            }

            update_option(SyncScheduler::OPTION_INTERVAL, $seconds, false);
        }

        $newIntervalSeconds = SyncScheduler::resolveInterval();
        $intervalChanged = $newIntervalSeconds !== $previousIntervalSeconds;

        if (isset($_POST['wp_umbrella_activity_log_enabled']) && $_POST['wp_umbrella_activity_log_enabled'] === '1') {
            update_option('wp_umbrella_activity_log_enabled', true);

            if ($intervalChanged) {
                (new SyncScheduler())->unschedule();
            }

            (new SyncScheduler())->schedule();
        } else {
            update_option('wp_umbrella_activity_log_enabled', false);
            (new SyncScheduler())->unschedule();
        }

        $options = $this->optionService->getOptions([
            'secure' => false,
        ]);

        if (isset($_POST['secret_token']) && $_POST['secret_token'] !== self::SECURED_VALUE && strlen($_POST['secret_token']) < 400) {
            $options['secret_token'] = !empty($_POST['secret_token']) ? wp_umbrella_get_service('WordPressContext')->getHash(sanitize_text_field($_POST['secret_token'])) : '';
        }

        if (isset($_POST['project_id']) && $_POST['project_id'] !== self::SECURED_VALUE && strlen($_POST['project_id']) < 100) {
            $options['project_id'] = isset($_POST['project_id']) ? sanitize_text_field($_POST['project_id']) : '';
        }

        if (isset($_POST['request_token']) && $_POST['request_token'] !== self::SECURED_VALUE && strlen($_POST['request_token']) < 400) {
            $options['request_token'] = !empty($_POST['request_token']) ? sanitize_text_field($_POST['request_token']) : '';
        }

        $this->optionService->setOptions($options);
        wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
        return;
    }

    /**
     * Register setting options.
     *
     * @see admin_init
     */
    public function init()
    {
        register_setting(WP_UMBRELLA_OPTION_GROUP, WP_UMBRELLA_SLUG, [$this, 'parseArgs']);
    }

    public function repairAjax()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Forbidden.', 'wp-health')], 403);
        }

        check_ajax_referer('wp_umbrella_repair_ajax');

        $result = wp_umbrella_get_service('PairingService')->runPairing();

        if ($result) {
            wp_send_json_success(['code' => 'success']);
        }

        wp_send_json_error([
            'code' => 'noop',
            'message' => __('Reconnection did not complete. The site stays connected via the legacy path; you can retry.', 'wp-health'),
        ]);
    }

    public function regenerateSecretToken()
    {
        if (!isset($_POST['_wpnonce'])) {
            wp_redirect(admin_url());
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_redirect(admin_url());
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_regenerate_secret_token')) {
            wp_redirect(admin_url());
            return;
        }

        $secretToken = wp_umbrella_generate_random_string(128);

        if (!wp_umbrella_is_new_hash()) {
            wp_umbrella_init_new_hash();
        }

        $options = wp_umbrella_get_options([
            'secure' => false
        ]);

        $options['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);
        wp_umbrella_get_service('Option')->setOptions($options);

        wp_load_alloptions(true);

        $responseValidateSecret = wp_umbrella_get_service('Projects')->validateSecretToken([
            'base_url' => site_url(),
            'rest_url' => rest_url(),
            'secret_token' => $secretToken,
            'save' => true
        ], wp_umbrella_get_api_key());

        if (!is_array($responseValidateSecret) || !isset($responseValidateSecret['success'])) {
            unset($options['secret_token']);
            wp_umbrella_get_service('Option')->setOptions($options);
            wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
            return;
        }

        if (!$responseValidateSecret['success']) {
            unset($options['secret_token']);
            wp_umbrella_get_service('Option')->setOptions($options);
            wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
            return;
        }

        $options['secret_token'] = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);

        $requestToken = wp_umbrella_request_token_from_response($responseValidateSecret);
        if ($requestToken) {
            $options['request_token'] = $requestToken;
            $options['api_key'] = '';
        }

        wp_umbrella_get_service('Option')->setOptions($options);
        wp_redirect(admin_url('/options-general.php?page=wp-umbrella-settings&support=1'));
        return;
    }

    /**
     * Callback register_setting for parseArgs options.
     *
     * @param array $options
     *
     * @return array
     */
    public function parseArgs($options)
    {
        $optionsBdd = $this->optionService->getOptions([
            'secure' => false,
        ]);
        $newOptions = wp_parse_args($options, $optionsBdd);

        return $newOptions;
    }
}
