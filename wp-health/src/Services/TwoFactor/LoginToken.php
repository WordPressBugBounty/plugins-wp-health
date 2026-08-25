<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class LoginToken
{
    const PREFIX = 'wp_umbrella_2fa_login_';

    const TTL = 600;

    /**
     * @param int   $userId
     * @param array $context
     *
     * @return string
     */
    public function issue($userId, array $context = [])
    {
        $token = bin2hex(random_bytes(32));

        set_transient($this->key($token), [
            'userId' => (int) $userId,
            'rememberme' => !empty($context['rememberme']),
            'redirectTo' => isset($context['redirectTo']) ? (string) $context['redirectTo'] : '',
        ], self::TTL);

        return $token;
    }

    /**
     * @param string $token
     *
     * @return array|null
     */
    public function peek($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $payload = get_transient($this->key($token));

        if (!is_array($payload) || empty($payload['userId'])) {
            return null;
        }

        return $payload;
    }

    /**
     * @param string $token
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function attach($token, $key, $value)
    {
        $payload = $this->peek($token);

        if ($payload === null) {
            return false;
        }

        $payload[$key] = $value;

        return (bool) set_transient($this->key($token), $payload, self::TTL);
    }

    /**
     * @param string $token
     *
     * @return array|null
     */
    public function consume($token)
    {
        $payload = $this->peek($token);

        if ($payload === null) {
            return null;
        }

        delete_transient($this->key($token));

        return $payload;
    }

    /**
     * @param string $token
     *
     * @return void
     */
    public function revoke($token)
    {
        if (!is_string($token) || $token === '') {
            return;
        }

        delete_transient($this->key($token));
    }

    /**
     * @param string $token
     *
     * @return string
     */
    protected function key($token)
    {
        return self::PREFIX . hash('sha256', $token);
    }
}
