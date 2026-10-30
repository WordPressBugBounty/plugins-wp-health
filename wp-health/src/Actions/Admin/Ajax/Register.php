<?php
namespace WPUmbrella\Actions\Admin\Ajax;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Actions\Admin\Option;

class Register implements ExecuteHooksBackend
{
    protected $registerService;

    public function __construct()
    {
        $this->registerService = wp_umbrella_get_service('Register');
    }

    public function hooks()
    {
        add_action('wp_ajax_wp_umbrella_register', [$this, 'register']);
    }

    public function register()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_register')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!Option::canWriteCredentials()) {
            wp_send_json_error([
                'code' => 'network_admin_required',
            ]);
            exit;
        }

        if (!isset($_POST['email'], $_POST['password'], $_POST['lastname'], $_POST['firstname'])) {
            wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
        }

        try {
            $result = $this->registerService->register([
                'email' => sanitize_email($_POST['email']),
                'password' => $_POST['password'],
                'firstname' => sanitize_text_field($_POST['firstname']),
                'lastname' => sanitize_text_field($_POST['lastname']),
                'newsletters' => isset($_POST['newsletters']) && 'true' === $_POST['newsletters'],
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ]);
            exit;
        }

        if (!isset($result['success']) || !$result['success']) {
            wp_send_json_success($result);

            return;
        }

        wp_send_json_success([
            'user' => $result,
        ]);
    }
}
