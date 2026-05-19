<?php
namespace WPUmbrella\Controller\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Models\AbstractController;

class PairChallengeController extends AbstractController
{
    const TOKEN_PREFIX = 'wpu_rt_';

    const TOKEN_BYTE_LENGTH = 32;

    public function executeGet($params)
    {
        $nonce = isset($params['nonce']) ? $params['nonce'] : null;

        if (!is_string($nonce) || $nonce === '') {
            return $this->returnResponse([
                'code' => 'missing_nonce',
            ], 400);
        }

        $headers = wp_umbrella_get_headers();
        $token = wp_umbrella_get_service('BearerTokenExtractor')->fromHeaderValue(
            isset($headers['authorization']) ? $headers['authorization'] : null
        );

        if ($token === null || strpos($token, self::TOKEN_PREFIX) !== 0) {
            return $this->returnResponse([
                'code' => 'missing_bearer',
            ], 400);
        }

        $encoded = substr($token, strlen(self::TOKEN_PREFIX));
        $standard = strtr($encoded, '-_', '+/');
        $padding = strlen($standard) % 4;
        if ($padding > 0) {
            $standard .= str_repeat('=', 4 - $padding);
        }
        $tokenBytes = base64_decode($standard, true);

        if ($tokenBytes === false || strlen($tokenBytes) !== self::TOKEN_BYTE_LENGTH) {
            return $this->returnResponse([
                'code' => 'invalid_token',
            ], 400);
        }

        $signature = hash_hmac('sha256', $nonce, $tokenBytes);

        return $this->returnResponse([
            'nonce' => $nonce,
            'signature' => $signature,
        ]);
    }
}
