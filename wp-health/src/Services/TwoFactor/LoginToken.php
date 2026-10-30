<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class LoginToken
{
    const PREFIX = 'wp_umbrella_2fa_login_';

    const TTL = 600;

    const SWEEP_BATCH = 20;

    /**
     * @param int   $userId
     * @param array $context
     *
     * @return string
     */
    public function issue($userId, array $context = [])
    {
        $this->sweepExpired();

        $token = bin2hex(random_bytes(32));

        add_option($this->key($token), [
            'userId' => (int) $userId,
            'rememberme' => !empty($context['rememberme']),
            'redirectTo' => isset($context['redirectTo']) ? (string) $context['redirectTo'] : '',
            'loginQuery' => isset($context['loginQuery']) && is_array($context['loginQuery']) ? $context['loginQuery'] : [],
            'expiresAt' => time() + self::TTL,
        ], '', false);

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

        $key = $this->key($token);
        $payload = get_option($key);

        if (!is_array($payload) || empty($payload['userId'])) {
            return null;
        }

        if ($this->isExpired($payload)) {
            delete_option($key);

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

        return (bool) update_option($this->key($token), $payload, false);
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

        if (!delete_option($this->key($token))) {
            return null;
        }

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

        delete_option($this->key($token));
    }

    /**
     * Every token gets the same TTL, so the oldest rows are the first to expire.
     *
     * @return void
     */
    protected function sweepExpired()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
                $wpdb->esc_like(self::PREFIX) . '%',
                self::SWEEP_BATCH
            )
        );

        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $payload = maybe_unserialize($row->option_value);

            if (is_array($payload) && !$this->isExpired($payload)) {
                return;
            }

            delete_option($row->option_name);
        }
    }

    /**
     * @param array $payload
     *
     * @return bool
     */
    protected function isExpired(array $payload)
    {
        return empty($payload['expiresAt']) || (int) $payload['expiresAt'] < time();
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
