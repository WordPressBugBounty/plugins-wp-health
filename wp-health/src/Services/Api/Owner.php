<?php
namespace WPUmbrella\Services\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Owner extends BaseClient
{
    protected $ownerImplicit = null;

    /**
     * Fallback on owner api key wp_remote_get.
     *
     * @param string $apiKey
     * @param string $apiKey
     *
     * @return null|array
     */
    public function getOwnerByApiKeyCURLV2($apiKey, $url = null)
    {
        if (!$url) {
            $url = WP_UMBRELLA_NEW_API_URL . '/v1/me';
        }

        try {
            $authorization = 'Authorization: Bearer ' . $apiKey;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json', $authorization
            ]);

            $result = curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode($result, true);

        return $body;
    }

    /**
    * @param string $apiKey
    *
    * @return array
    */
    public function validateApiKeyOnApplication($data)
    {
        try {
            $apiKey = $data['api_key'];

            $response = wp_remote_get(WP_UMBRELLA_NEW_API_URL . '/v1/external/me', [
                'headers' => $this->getHeadersV2($apiKey),
                'sslverify' => false,
                'timeout' => 40,
            ]);
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['code']) && apply_filters('wp_umbrella_get_owner_try_curl', false)) {
            return $this->getOwnerByApiKeyCURLV2($apiKey, WP_UMBRELLA_NEW_API_URL . '/v1/external/me');
        }

        return $body;
    }

    /**
     * @param string $apiKey
     *
     * @return array
     */
    public function getOwnerByApiKeyV2($apiKey)
    {
        try {
            $response = wp_remote_get(WP_UMBRELLA_NEW_API_URL . '/v1/me', [
                'headers' => $this->getHeadersV2($apiKey),
                'sslverify' => false,
                'timeout' => 20,
            ]);
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['code']) && apply_filters('wp_umbrella_get_owner_try_curl', false)) {
            return $this->getOwnerByApiKeyCURLV2($apiKey);
        }

        return $body;
    }

    /**
     * @return array|null
     */
    public function getOwnerImplicitApiKey()
    {
        if ($this->ownerImplicit !== null) {
            return $this->ownerImplicit;
        }

        if (!wp_umbrella_get_api_key()) {
            return null;
        }

        $response = $this->getOwnerByApiKeyV2(wp_umbrella_get_api_key());

        if (!isset($response['result'])) {
            return $response;
        }

        $this->ownerImplicit = $response['result'];

        return $this->ownerImplicit;
    }
}
