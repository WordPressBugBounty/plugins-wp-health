<?php
namespace WPUmbrella\Actions\Admin\Ajax;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

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
        add_action('wp_ajax_wp_health_login', [$this, 'login']);
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

    public function login()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_health_login')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['email']) || !isset($_POST['password'])) {
            wp_send_json_error([
                'code' => 'missing_parameters',
            ]);
            exit;
        }

        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);

        try {
            $newUser = $this->registerService->login($email, $password);
        } catch (\Exception $e) {
            wp_send_json_error([
                'code' => 'unknown_error',
            ]);
            exit;
        }

        if (null === $newUser) {
            wp_send_json_error([
                'code' => 'no_user',
            ]);
        }

        wp_send_json_success([
            'user' => $newUser,
        ]);
    }
}
