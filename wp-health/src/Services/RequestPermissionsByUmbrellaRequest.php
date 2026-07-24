<?php
namespace WPUmbrella\Services;

use WPUmbrella\Core\UmbrellaRequest;

class RequestPermissionsByUmbrellaRequest
{
    /**
     * @param UmbrellaRequest $request
     * @return boolean
     */
    public function isSignatureAuthorized(UmbrellaRequest $request)
    {
        return $this->isSignatureValid($request);
    }

    public function isOnlyTokenAuthorized(UmbrellaRequest $request)
    {
        if ($this->isSignatureValid($request)) {
            return true;
        }

        if (wp_umbrella_is_signature_only()) {
            return false;
        }

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
        if ($this->isSignatureValid($request)) {
            return true;
        }

        // One-click login signed via request params (a browser form cannot set
        // headers). Checked before the signature-only gate so a signed login
        // authorizes without the secret_token ever reaching the browser.
        if ($request->getAction() === '/v1/login' && $this->isLoginSignatureValid($request)) {
            return true;
        }

        if (wp_umbrella_is_signature_only()) {
            return false;
        }

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
    protected function isSignatureValid(UmbrellaRequest $request)
    {
        $verifier = wp_umbrella_get_service('SignedRequestVerifier');

        if (!$verifier->hasSignatureHeaders($request->getHeaders())) {
            // A signed one-click login carries its signature as request params,
            // not headers, so this header check is expected to miss. Don't log
            // noise that looks like a failure right before the param check runs.
            if (!$request->getParam('x-umb-login-sig')) {
                wp_umbrella_debug_log(
                    'auth ' . strtoupper($request->getMethod()) . ' ' . $request->getRequestPath()
                    . ': no signature headers, key_state=' . wp_umbrella_get_key_state()
                );
            }
            return false;
        }

        return $verifier->verify(
            $request->getHeaders(),
            $request->getMethod(),
            $request->getRequestPath(),
            $request->getRawBody()
        );
    }

    protected function isLoginSignatureValid(UmbrellaRequest $request)
    {
        $signature = $request->getParam('x-umb-login-sig');
        if (!$signature) {
            return false;
        }

        return wp_umbrella_get_service('SignedRequestVerifier')->verifyLogin(
            $request->getParam('user_id'),
            $signature,
            $request->getParam('x-umb-login-ts'),
            $request->getParam('x-umb-login-nonce'),
            $request->getParam('x-umb-login-keyid')
        );
    }

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
