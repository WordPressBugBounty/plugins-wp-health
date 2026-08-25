<?php
namespace WPUmbrella\Services\TwoFactor;

use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;

if (!defined('ABSPATH')) {
    exit;
}

class ChallengeThrottle
{
    const USER_PREFIX = 'wp_umbrella_2fa_thr_user_';

    const IP_PREFIX = 'wp_umbrella_2fa_thr_ip_';

    const MAX_USER_FAILURES = 10;

    const MAX_IP_FAILURES = 30;

    const WINDOW = 900;

    /**
     * @param int $userId
     *
     * @return bool
     */
    public function isLocked($userId)
    {
        $userCount = $this->getCount($this->userKey($userId));

        return $this->overCeiling($userCount + 1, $this->ipCount() + 1, $userCount);
    }

    /**
     * Counts the attempt and answers whether it is over a ceiling.
     *
     * @param int $userId
     *
     * @return bool
     */
    public function registerFailure($userId)
    {
        $userCount = $this->increment($this->userKey($userId));

        $ipKey = $this->ipKey();
        $ipCount = $ipKey !== null ? $this->increment($ipKey) : 0;

        return $this->overCeiling($userCount, $ipCount, $userCount - 1);
    }

    /**
     * @param int $userId
     *
     * @return void
     */
    public function clear($userId)
    {
        delete_transient($this->userKey($userId));
    }

    /**
     * @param int $userId
     *
     * @return int
     */
    public function retriesLeft($userId)
    {
        $left = self::MAX_USER_FAILURES - $this->getCount($this->userKey($userId));

        return $left > 0 ? $left : 0;
    }

    /**
     * @param int $userCount
     * @param int $ipCount
     * @param int $ownFailures
     *
     * @return bool
     */
    protected function overCeiling($userCount, $ipCount, $ownFailures)
    {
        if ($userCount > self::MAX_USER_FAILURES) {
            return true;
        }

        return $ownFailures >= 1 && $ipCount > self::MAX_IP_FAILURES;
    }

    /**
     * @param string $key
     *
     * @return int
     */
    protected function increment($key)
    {
        $this->dropExpiredWindow($key);

        if (wp_using_ext_object_cache()) {
            if (wp_cache_add($key, 1, 'transient', self::WINDOW)) {
                return 1;
            }

            $count = wp_cache_incr($key, 1, 'transient');

            return is_numeric($count) ? (int) $count : $this->getCount($key);
        }

        return $this->incrementOptionRow($key);
    }

    /**
     * @param string $key
     *
     * @return int
     */
    protected function incrementOptionRow($key)
    {
        global $wpdb;

        $option = '_transient_' . $key;
        $timeout = '_transient_timeout_' . $key;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
                $timeout,
                (string) (time() + self::WINDOW)
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off')"
                . " ON DUPLICATE KEY UPDATE option_value = option_value + 1",
                $option
            )
        );

        wp_cache_delete($option, 'options');
        wp_cache_delete($timeout, 'options');
        wp_cache_delete('notoptions', 'options');

        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option)
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param string $key
     *
     * @return void
     */
    protected function dropExpiredWindow($key)
    {
        get_transient($key);
    }

    /**
     * @param string $key
     *
     * @return int
     */
    protected function getCount($key)
    {
        $count = get_transient($key);

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return int
     */
    protected function ipCount()
    {
        $ipKey = $this->ipKey();

        return $ipKey === null ? 0 : $this->getCount($ipKey);
    }

    /**
     * @param int $userId
     *
     * @return string
     */
    protected function userKey($userId)
    {
        return self::USER_PREFIX . (int) $userId;
    }

    /**
     * @return string|null
     */
    protected function ipKey()
    {
        $ip = ClientIpResolver::resolve();

        if ($ip === null || $ip === '') {
            return null;
        }

        return self::IP_PREFIX . md5($ip);
    }
}
