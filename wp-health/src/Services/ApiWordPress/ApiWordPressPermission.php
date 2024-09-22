<?php
namespace WPUmbrella\Services\ApiWordPress;

class ApiWordPressPermission
{
    public function isSecretTokenAuthorized($userSecretToken, $options = [])
    {
        if (!$userSecretToken || empty($userSecretToken)) {
            return ['authorized' => false, 'code' => 'api_key_empty', 'message' => 'API Key is empty'];
        }

        $withCache = $options['with_cache'] ?? true;
        $secretTokenSave = wp_umbrella_get_secret_token();

        if ((!$secretTokenSave || empty($secretTokenSave)) && defined('WP_UMBRELLA_SECRET_TOKEN')) {
            $secretTokenSave = WP_UMBRELLA_SECRET_TOKEN;
        }

        if (!$secretTokenSave && !$withCache) {
            $secretTokenSave = wp_umbrella_get_service('Option')->getSecretTokenWithoutCache();
        }

        if (!hash_equals($secretTokenSave, $userSecretToken)) {
            return ['authorized' => false, 'code' => 'not_authorized', 'message' => 'API Key not authorize'];
        }

        return ['authorized' => true];
    }

    /**
     *
     * @param string $userToken
     * @return array
     */
    public function isTokenAuthorized($userToken, $options = [])
    {
        $allow = apply_filters('wp_umbrella_allow_access_api', true);
        if (!$allow) {
            return ['authorized' => false, 'code' => 'not_allowed', 'message' => 'Not authorize access data'];
        }

        if (!$userToken || empty($userToken)) {
            return ['authorized' => false, 'code' => 'api_key_empty', 'message' => 'API Key is empty'];
        }

        $withCache = $options['with_cache'] ?? true;

        $apiKeySave = wp_umbrella_get_api_key();
        if ((!$apiKeySave || empty($apiKeySave)) && defined('WP_UMBRELLA_API_KEY')) {
            $apiKeySave = WP_UMBRELLA_API_KEY;
        }
        if (!$apiKeySave && !$withCache) {
            $apiKeySave = wp_umbrella_get_service('Option')->getApiKeyWithoutCache();
        }

        if (!$apiKeySave) {
            return ['authorized' => false, 'code' => 'api_key_empty', 'message' => 'API Key is empty'];
        }

        if (!hash_equals($apiKeySave, $userToken)) {
            return ['authorized' => false, 'code' => 'not_authorized', 'message' => 'API Key not authorize'];
        }

        return ['authorized' => true];
    }

    /**
     *
     * @param string $token
     * @return array
     */
    public function isFullyAuthorized($token, $secretToken, $options = [])
    {
        $allow = apply_filters('wp_umbrella_allow_access_api', true);
        if (!$allow) {
            return ['authorized' => false, 'code' => 'not_allowed', 'message' => 'Not authorize access data'];
        }

        if (!$token || empty($token) || !$secretToken || empty($secretToken)) {
            return ['authorized' => false, 'code' => 'api_key_empty', 'message' => 'API Key is empty'];
        }

        $tokenAuthorized = $this->isTokenAuthorized($token, $options);

        if (!$tokenAuthorized['authorized']) {
            return $tokenAuthorized;
        }

        $secretTokenAuthorized = $this->isSecretTokenAuthorized($secretToken, $options);

        if (!$secretTokenAuthorized['authorized']) {
            return $secretTokenAuthorized;
        }

        return ['authorized' => true];
    }
}
