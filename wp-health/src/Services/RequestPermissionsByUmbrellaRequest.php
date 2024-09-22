<?php
namespace WPUmbrella\Services;

use WPUmbrella\Core\UmbrellaRequest;

class RequestPermissionsByUmbrellaRequest
{
    /**
     * @param UmbrellaRequest $request
     * @return boolean
     */
    public function isOnlyTokenAuthorized(UmbrellaRequest $request)
    {
        $token = $request->getToken();
        $response = wp_umbrella_get_service('ApiWordPressPermission')->isTokenAuthorized($token);

        if (!isset($response['authorized'])) {
            return false;
        }

        return $response['authorized'];
    }

    /**
     * @param UmbrellaRequest $request
     * @return boolean
     */
    public function isFullyAuthorized(UmbrellaRequest $request)
    {
        $token = $request->getToken();
        $secretToken = $request->getSecretToken();

        $action = $request->getAction();
        if ($action === '/v1/login') {
            if (!$secretToken) {
                $secretToken = $request->getParam('x-secret-token');
            }

            if (!$secretToken) {
                $secretToken = $request->getParam('x-auth-token');
            }

            if (!$token) {
                $token = $request->getParam('x-umbrella');
            }
        }

        $response = wp_umbrella_get_service('ApiWordPressPermission')->isFullyAuthorized($token, $secretToken, [
            'with_cache' => $action !== '/v1/validation-application-token'
        ]);

        if (!isset($response['authorized'])) {
            return false;
        }

        return $response['authorized'];
    }
}
