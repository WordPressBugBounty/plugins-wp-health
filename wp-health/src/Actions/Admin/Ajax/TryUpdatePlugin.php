<?php
namespace WPUmbrella\Actions\Admin\Ajax;

use WPUmbrella\Core\Hooks\ExecuteHooks;

class TryUpdatePlugin implements ExecuteHooks
{
    public function hooks()
    {
        add_action('wp_ajax_wp_umbrella_dashboard_admin_request', [$this, 'adminRequest']);

        add_action('wp_umbrella_dashboard_admin_request', [$this, 'handle']);
    }

    public function adminRequest()
    {
        // Make sure required values are set.
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $hash = isset($_POST['hash']) ? $_POST['hash'] : '';

        // Nonce and hash are required.
        if (empty($nonce) || empty($hash)) {
            wp_send_json_error(
                [
                    'code' => 'invalid_params',
                    'message' => __('Required parameters are missing', 'wp-health'),
                ]
            );
        }

        // If nonce check failed.
        if (!wp_verify_nonce($nonce, 'wp_umbrella_dashboard_admin_request')) {
            wp_send_json_error(
                [
                    'code' => 'nonce_failed',
                    'message' => __('Admin request nonce check failed', 'wp-health'),
                ]
            );
        }

        // Get request data from cache.
        $data = get_site_transient($hash);

        // Make sure action and params are set.
        if (false === $data) {
            wp_send_json_error(
                [
                    'code' => 'invalid_request',
                    'message' => __('Invalid request.', 'wp-health'),
                ]
            );
        }

		delete_site_transient($hash);

        do_action('wp_umbrella_dashboard_admin_request', $data);
    }

    public function handle($data)
    {
        // Only if all values are set.
        if (
                isset($data['type'], $data['file'], $data['from'], $data['action'])
                && 'upgrader' === $data['from']
                && 'upgrade' === $data['action']
            ) {
            // Skip sync, hub remote calls are recorded locally.
            if (!defined('WP_UMBRELLA_REMOTE_SKIP_SYNC')) {
                define('WP_UMBRELLA_REMOTE_SKIP_SYNC', true);
            }

            // All good. Process the request.
            $managePlugin = \wp_umbrella_get_service('ManagePlugin');

            try {
                $result = $managePlugin->update($data['file'], [
                    'try_ajax' => false
                ]);
                wp_send_json($result);
            } catch (\Exception $e) {
                wp_send_json([
                    'code' => 'unknown_error',
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
