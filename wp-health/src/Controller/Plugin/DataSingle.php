<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class DataSingle extends AbstractController
{
    const NONCE_ACTION = 'wp_umbrella_plugin_data_single_admin_request';

    public static function getPluginDataByAjaxRouting($plugin)
    {
        // Make sure required values are set.
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        $plugin = isset($_POST['plugin']) ? $_POST['plugin'] : '';

        wp_umbrella_get_service('RequestSettings')->setupAdminConstants();
        wp_umbrella_get_service('RequestSettings')->setupAdmin();

        // Nonce and hash are required.
        if (empty($nonce)) {
            wp_send_json_error(
                [
                    'code' => 'invalid_params',
                    'message' => __('Required parameters are missing', 'wp-health'),
                ]
            );
        }

        // If nonce check failed.
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(
                [
                    'code' => 'nonce_failed',
                    'message' => __('Admin request nonce check failed', 'wp-health'),
                ]
            );
        }

        $plugin = wp_umbrella_get_service('PluginsProvider')->getPlugin($plugin);

        wp_send_json($plugin);
    }

    protected function sendAdminRequest($plugin)
    {
        // Create nonce.
        $nonce = wp_create_nonce(self::NONCE_ACTION);

        // Request arguments.
        $args = [
            'timeout' => 45,
            'cookies' => [],
            'sslverify' => false,
            'body' => [
                'action' => self::NONCE_ACTION,
                'nonce' => $nonce,
                'plugin' => $plugin,
            ],
        ];

        // Set cookies if required.
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $name => $value) {
                $args['cookies'][] = new \WP_Http_Cookie(compact('name', 'value'));
            }
        }

        // Make post request.
        $response = wp_remote_post(admin_url('admin-ajax.php'), $args);

        // If request not failed.
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            // Get response body.
            try {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    public function executeGet($params)
    {
        try {
            $plugin = isset($params['plugin']) ? $params['plugin'] : null;

            if (!$plugin) {
                return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
            }

            $response = $this->sendAdminRequest($plugin);

            return $this->returnResponse([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
