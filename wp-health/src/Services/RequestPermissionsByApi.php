<?php
namespace WPUmbrella\Services;

use WP_REST_Response;

class RequestPermissionsByApi
{
    /**
     * @param WP_REST_Request $request
     * @return boolean
     */
    public function isOnlyTokenAuthorized($request)
    {
        $token = $request->get_header('X-Umbrella');

        $response = wp_umbrella_get_service('ApiWordPressPermission')->isTokenAuthorized($token);

        if (!isset($response['authorized'])) {
            header('Cache-Control: no-cache');
            return new \WP_Error($response['code'], $response['message']);
        }

        return $response['authorized'];
    }

    /**
     * @param WP_REST_Request $request
     * @return boolean
     */
    public function isFullyAuthorized($request)
    {
        $token = $request->get_header('X-Umbrella');
        $secretToken = $request->get_header('X-Secret-Token');

        if (!$secretToken) { // O2Switch fallback
            $secretToken = $request->get_header('X-Auth-Token');
        }

        $action = $request->get_param('X-Action');

        if ($action === '/v1/login') {
            if (!$secretToken) {
                $secretToken = $request->get_param('x-secret-token');
            }

            if (!$secretToken) {
                $secretToken = $request->get_param('x-auth-token');
            }

            if (!$token) {
                $token = $request->get_param('x-umbrella');
            }
        }

        $secretToken = wp_umbrella_get_service('WordPressContext')->getHash($secretToken);

        $response = wp_umbrella_get_service('ApiWordPressPermission')->isFullyAuthorized($token, $secretToken);

        if (!isset($response['authorized'])) {
            header('Cache-Control: no-cache');
            return new \WP_Error($response['code'], $response['message']);
        }

        return $response['authorized'];
    }
}
