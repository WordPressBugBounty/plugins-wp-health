<?php
namespace WPUmbrella\Services\Pairing;

if (!defined('ABSPATH')) {
    exit;
}

class PairingService
{
    const MIN_PAIRING_VERSION = '2.24.0';

    const REQUEST_TIMEOUT = 30;

    public function shouldStartPairing()
    {
        if (!defined('WP_UMBRELLA_VERSION')) {
            return false;
        }

        $minVersion = apply_filters('wp_umbrella_pairing_min_version', self::MIN_PAIRING_VERSION);

        if (version_compare(WP_UMBRELLA_VERSION, $minVersion, '<')) {
            return false;
        }

        $optionService = wp_umbrella_get_service('Option');

        $apiKey = $optionService->getApiKeyWithoutCache();
        if (empty($apiKey)) {
            return false;
        }

        $requestToken = $optionService->getRequestTokenWithoutCache();
        if (!empty($requestToken)) {
            return false;
        }

        return true;
    }

    public function runPairing()
    {
        if (!$this->shouldStartPairing()) {
            return false;
        }

        $optionService = wp_umbrella_get_service('Option');
        $apiKey = $optionService->getApiKeyWithoutCache();

        $pairResponse = $this->callPair($apiKey);

        if (!is_array($pairResponse)) {
            return false;
        }

        $payload = isset($pairResponse['data']) && is_array($pairResponse['data'])
            ? $pairResponse['data']
            : $pairResponse;

        if (!isset($payload['request_token']) || !isset($payload['project_id'])) {
            return false;
        }

        $requestToken = $payload['request_token'];
        $projectId = $payload['project_id'];

        if (!is_string($requestToken) || $requestToken === '') {
            return false;
        }

        if (is_int($projectId)) {
            $projectId = (string) $projectId;
        }

        if (!is_string($projectId) || $projectId === '') {
            return false;
        }

        $persisted = $this->persistRequestToken($requestToken);
        if (!$persisted) {
            return false;
        }

        $ackResponse = $this->callAck($requestToken, $projectId);
        if ($ackResponse === null) {
            $this->persistRequestToken('');
            return false;
        }

        $this->persistSigningKey($ackResponse);

        return $this->wipeApiKey();
    }

    public function wipeApiKey()
    {
        $optionService = wp_umbrella_get_service('Option');

        $options = $optionService->getOptions(['secure' => false]);

        if (!isset($options['api_key']) || $options['api_key'] === '') {
            return true;
        }

        $options['api_key'] = '';
        $optionService->setOptions($options);

        return true;
    }

    public function persistRequestToken($requestToken, $projectId = null)
    {
        $optionService = wp_umbrella_get_service('Option');

        $options = $optionService->getOptions(['secure' => false]);
        $options['request_token'] = $requestToken;
        if ($projectId !== null) {
            $options['project_id'] = is_int($projectId) ? (string) $projectId : $projectId;
        }
        $optionService->setOptions($options);

        $stored = $optionService->getRequestTokenWithoutCache();

        return is_string($stored) && hash_equals($requestToken, $stored);
    }

    protected function callPair($apiKey)
    {
        $url = WP_UMBRELLA_NEW_API_URL . '/v1/projects/pair';

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $apiKey),
            ],
            'body' => wp_json_encode([
                'site_url' => site_url(),
                'plugin_version' => defined('WP_UMBRELLA_VERSION') ? WP_UMBRELLA_VERSION : '',
            ]),
            'sslverify' => false,
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function callAck($requestToken, $projectId)
    {
        $url = WP_UMBRELLA_NEW_API_URL . '/v1/projects/pair/ack';

        $bodyProjectId = ctype_digit((string) $projectId) ? (int) $projectId : $projectId;

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $requestToken),
                'X-Project' => site_url(),
            ],
            'body' => wp_json_encode([
                'project_id' => $bodyProjectId,
                'plugin_version' => defined('WP_UMBRELLA_VERSION') ? WP_UMBRELLA_VERSION : '',
            ]),
            'sslverify' => false,
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function persistSigningKey($ackResponse)
    {
        $payload = isset($ackResponse['data']) && is_array($ackResponse['data'])
            ? $ackResponse['data']
            : $ackResponse;

        $signingKey = wp_umbrella_signing_key_from_response($payload);
        if (!$signingKey) {
            return;
        }

        $optionService = wp_umbrella_get_service('Option');

        $options = $optionService->getOptions(['secure' => false]);
        $options['public_key'] = $signingKey['public_key'];
        $options['key_id'] = $signingKey['key_id'];
        $options['key_state'] = 'dual';
        $optionService->setOptions($options);
    }
}
