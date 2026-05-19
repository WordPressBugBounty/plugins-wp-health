<?php
namespace WPUmbrella\Services\Api;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseClient
{
    protected function canRequestApi() : bool
    {
        if (!wp_umbrella_get_api_key() && !wp_umbrella_get_request_token()) {
            return false;
        }

        if (!wp_umbrella_get_secret_token()) {
            return false;
        }

        return true;
    }

    public function getHeadersV2($apiKey = null, $options = [], $curlVersion = false)
    {
        $type = isset($options['type']) ? $options['type'] : 'json';

        switch ($type) {
            case 'json':
            default:
                $type = 'application/json';
                break;
            case 'file':
                $type = 'multipart/form-data';
                break;
        }

        $headers = [
            'Content-Type' => $type,
            'X-Project' => site_url(),
            'X-Multisite' => is_multisite(),
            'X-Version' => WP_UMBRELLA_VERSION
        ];

        if ($curlVersion) {
            $headers = [
                'Content-Type: ' . $type,
                'X-Project: ' . site_url(),
                'X-Multisite: ' . is_multisite(),
                'X-Version: ' . WP_UMBRELLA_VERSION,
            ];
        }

        if (!$apiKey) {
            $apiKey = wp_umbrella_get_outbound_bearer();
        }

        if ($curlVersion) {
            $headers[] = sprintf('Authorization: Bearer %s', $apiKey);
        } else {
            $headers['Authorization'] = sprintf('Bearer %s', $apiKey);
        }

        return $headers;
    }
}
