<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class TestPingWorker implements ExecuteHooksBackend
{
    const ACTION = 'wp_umbrella_test_ping';
    const NONCE = 'wp_umbrella_test_ping';
    const OPTION_KEY = 'wp_umbrella_last_test_ping';
    const ENDPOINT_PATH = '/v1/me';
    const TIMEOUT_SECONDS = 10;

    public function hooks()
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle()
    {
        $redirect = admin_url('options-general.php?page=wp-umbrella-settings&support=1#wpu-test-ping');

        if (!isset($_POST['_wpnonce'])) {
            $this->storeResult([
                'status' => 'error',
                'reason' => 'missing_nonce',
                'message' => __('Missing security nonce on the request. Reload the page and try again.', 'wp-health'),
            ]);
            wp_safe_redirect($redirect);
            exit;
        }

        if (!current_user_can('manage_options')) {
            $this->storeResult([
                'status' => 'error',
                'reason' => 'forbidden',
                'message' => __('Your user is not allowed to run this action.', 'wp-health'),
            ]);
            wp_safe_redirect($redirect);
            exit;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            $this->storeResult([
                'status' => 'error',
                'reason' => 'invalid_nonce',
                'message' => __('Security nonce expired or invalid. Reload the page and try again.', 'wp-health'),
            ]);
            wp_safe_redirect($redirect);
            exit;
        }

        $bearer = wp_umbrella_get_outbound_bearer();
        if (empty($bearer)) {
            $this->storeResult([
                'status' => 'error',
                'reason' => 'not_connected',
                'message' => __('Site not connected to WP Umbrella. Configure the plugin before running the ping.', 'wp-health'),
            ]);
            wp_safe_redirect($redirect);
            exit;
        }

        $url = WP_UMBRELLA_NEW_API_URL . self::ENDPOINT_PATH;
        $headers = wp_umbrella_get_service('Owner')->getHeadersV2($bearer);

        $start = microtime(true);
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => self::TIMEOUT_SECONDS,
            'sslverify' => true,
        ]);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            $this->storeResult([
                'status' => 'error',
                'reason' => 'transport',
                'message' => $response->get_error_message(),
                'duration_ms' => $durationMs,
            ]);
            wp_safe_redirect($redirect);
            exit;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $bodyDecoded = is_string($body) ? json_decode($body, true) : null;
        $errorCode = is_array($bodyDecoded) && isset($bodyDecoded['code']) ? (string) $bodyDecoded['code'] : null;

        $this->storeResult([
            'status' => $code === 200 ? 'ok' : 'error',
            'reason' => $code === 200 ? 'reachable' : 'http_error',
            'http_code' => $code,
            'error_code' => $errorCode,
            'duration_ms' => $durationMs,
        ]);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function getLastResult()
    {
        wp_cache_delete(self::OPTION_KEY, 'options');
        $value = get_option(self::OPTION_KEY);
        if (!is_array($value)) {
            return null;
        }
        delete_option(self::OPTION_KEY);
        return $value;
    }

    protected function storeResult(array $result)
    {
        $result['timestamp'] = time();
        update_option(self::OPTION_KEY, $result, false);
    }
}
