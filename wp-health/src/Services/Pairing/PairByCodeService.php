<?php
namespace WPUmbrella\Services\Pairing;

if (!defined('ABSPATH')) {
    exit;
}

class PairByCodeService
{
    const REQUEST_TIMEOUT = 30;

    const ERROR_INVALID_CODE_FORMAT = 'invalid_code_format';
    const ERROR_INVALID_OR_EXPIRED_CODE = 'invalid_or_expired_code';
    const ERROR_WORKER_UNREACHABLE = 'worker_unreachable';
    const ERROR_CHALLENGE_FAILED = 'challenge_failed';
    const ERROR_INVALID_RESPONSE = 'invalid_response';
    const ERROR_PERSIST_FAILED = 'persist_failed';

    public function isValidCodeFormat($pairCode)
    {
        if (!is_string($pairCode) || $pairCode === '') {
            return false;
        }

        if (strlen($pairCode) < 16 || strlen($pairCode) > 512) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $pairCode);
    }

    public function snapshotPairingState()
    {
        $optionService = wp_umbrella_get_service('Option');
        $options = $optionService->getOptions(['secure' => false]);

        return [
            'api_key' => isset($options['api_key']) ? $options['api_key'] : '',
            'request_token' => isset($options['request_token']) ? $options['request_token'] : '',
            'project_id' => isset($options['project_id']) ? $options['project_id'] : '',
        ];
    }

    public function restorePairingState(array $snapshot)
    {
        $optionService = wp_umbrella_get_service('Option');

        $options = $optionService->getOptions(['secure' => false]);
        $options['api_key'] = isset($snapshot['api_key']) ? $snapshot['api_key'] : '';
        $options['request_token'] = isset($snapshot['request_token']) ? $snapshot['request_token'] : '';
        $options['project_id'] = isset($snapshot['project_id']) ? $snapshot['project_id'] : '';
        $optionService->setOptions($options);
    }

    public function clearPairingState()
    {
        $optionService = wp_umbrella_get_service('Option');

        $options = $optionService->getOptions(['secure' => false]);
        $options['api_key'] = '';
        $options['request_token'] = '';
        $optionService->setOptions($options);
    }

    public function pair($pairCode)
    {
        if (!$this->isValidCodeFormat($pairCode)) {
            return [
                'ok' => false,
                'error' => self::ERROR_INVALID_CODE_FORMAT,
                'message' => 'pair_code is missing or malformed',
            ];
        }

        $response = $this->callPair($pairCode);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'error' => self::ERROR_WORKER_UNREACHABLE,
                'message' => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200) {
            return $this->handleSuccess(is_array($body) ? $body : []);
        }

        return $this->mapErrorResponse($code, is_array($body) ? $body : []);
    }

    protected function callPair($pairCode)
    {
        $url = WP_UMBRELLA_PUBLIC_API_URL . '/partner/projects/pair';

        return wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'pair_code' => $pairCode,
                'site_url' => get_site_url(),
                'plugin_version' => defined('WP_UMBRELLA_VERSION') ? WP_UMBRELLA_VERSION : '',
            ]),
            'sslverify' => true,
            'timeout' => self::REQUEST_TIMEOUT,
        ]);
    }

    protected function handleSuccess(array $payload)
    {
        $envelope = isset($payload['data']) && is_array($payload['data'])
            ? $payload['data']
            : $payload;

        $requestToken = isset($envelope['request_token']) ? $envelope['request_token'] : null;
        $projectId = isset($envelope['project_id']) ? $envelope['project_id'] : null;

        if (!is_string($requestToken) || $requestToken === '') {
            return [
                'ok' => false,
                'error' => self::ERROR_INVALID_RESPONSE,
                'message' => 'request_token missing in response',
            ];
        }

        if (!is_string($projectId) && !is_int($projectId)) {
            return [
                'ok' => false,
                'error' => self::ERROR_INVALID_RESPONSE,
                'message' => 'project_id missing in response',
            ];
        }

        $pairingService = wp_umbrella_get_service('PairingService');

        if (!$pairingService->persistRequestToken($requestToken, $projectId)) {
            return [
                'ok' => false,
                'error' => self::ERROR_PERSIST_FAILED,
                'message' => 'could not persist request_token',
            ];
        }

        $pairingService->wipeApiKey();

        return [
            'ok' => true,
            'project_id' => $projectId,
            'request_token' => $requestToken,
        ];
    }

    protected function mapErrorResponse($status, array $body)
    {
        $apiCode = isset($body['code']) && is_string($body['code']) ? $body['code'] : '';
        $apiMessage = isset($body['message']) && is_string($body['message']) ? $body['message'] : '';

        if ($status === 401 || $apiCode === 'invalid_pair_code') {
            return [
                'ok' => false,
                'error' => self::ERROR_INVALID_OR_EXPIRED_CODE,
                'message' => $apiMessage !== '' ? $apiMessage : 'pair code is invalid, expired or already consumed',
            ];
        }

        if ($status === 502 || $apiCode === 'challenge_failed') {
            return [
                'ok' => false,
                'error' => self::ERROR_CHALLENGE_FAILED,
                'message' => $apiMessage !== '' ? $apiMessage : 'challenge failed',
            ];
        }

        if ($status >= 500) {
            return [
                'ok' => false,
                'error' => self::ERROR_WORKER_UNREACHABLE,
                'message' => $apiMessage !== '' ? $apiMessage : sprintf('worker returned status %d', $status),
            ];
        }

        return [
            'ok' => false,
            'error' => self::ERROR_INVALID_RESPONSE,
            'message' => $apiMessage !== '' ? $apiMessage : sprintf('unexpected status %d', $status),
        ];
    }

}
