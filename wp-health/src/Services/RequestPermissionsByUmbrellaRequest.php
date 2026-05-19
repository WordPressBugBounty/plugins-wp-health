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
        $bearer = $request->getAuthorizationBearer();
        if ($bearer !== null) {
            if ($this->isBearerHashSecretValid($bearer)) {
                return true;
            }
            if ($this->isApiKeyValid($bearer)) {
                return true;
            }
        }

        $token = $request->getToken();
        return $this->isApiKeyValid($token);
    }

    /**
     * @param UmbrellaRequest $request
     * @return boolean
     */
    public function isFullyAuthorized(UmbrellaRequest $request)
    {
        $action = $request->getAction();
        $options = ['with_cache' => $action !== '/v1/validation-application-token'];

        $bearer = $request->getAuthorizationBearer();
        if ($bearer === null) {
            $bearer = wp_umbrella_get_service('BearerTokenExtractor')->fromHeaderValue(
                $request->getParam('x-authorization')
            );
        }
        if ($bearer !== null && $this->isBearerHashSecretValid($bearer, $options)) {
            return true;
        }

        $token = $request->getToken();
        $secretToken = $request->getSecretToken();

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

        return $this->isFullAuthValid($token, $secretToken, $options);
    }

    /**
     * @param string|null $token
     * @return boolean
     */
    protected function isApiKeyValid($token)
    {
        $response = wp_umbrella_get_service('ApiWordPressPermission')->isTokenAuthorized($token);
        if (!isset($response['authorized'])) {
            return false;
        }
        return $response['authorized'];
    }

    /**
     * @param string|null $token
     * @param string|null $secretToken
     * @param array $options
     * @return boolean
     */
    protected function isFullAuthValid($token, $secretToken, $options)
    {
        $response = wp_umbrella_get_service('ApiWordPressPermission')->isFullyAuthorized($token, $secretToken, $options);
        if (!isset($response['authorized'])) {
            return false;
        }
        return $response['authorized'];
    }

    protected function isBearerHashSecretValid($bearer, $options = [])
    {
        if (empty($bearer)) {
            return false;
        }
        $hashedBearer = wp_umbrella_get_service('WordPressContext')->getHash($bearer);
        $response = wp_umbrella_get_service('ApiWordPressPermission')->isSecretTokenAuthorized($hashedBearer, $options);
        if (!isset($response['authorized'])) {
            return false;
        }
        return $response['authorized'];
    }
}
