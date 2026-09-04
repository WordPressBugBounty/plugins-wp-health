<?php
namespace WPUmbrella\Services\TwoFactor;

use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;
use WPUmbrella\Services\Hardening\TransientCounter;

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
     * @var TransientCounter
     */
    protected $counter;

    public function __construct()
    {
        $this->counter = new TransientCounter();
    }

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
        $userCount = $this->counter->increment($this->userKey($userId), self::WINDOW);

        $ipKey = $this->ipKey();
        $ipCount = $ipKey !== null ? $this->counter->increment($ipKey, self::WINDOW) : 0;

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
    protected function getCount($key)
    {
        return $this->counter->get($key);
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
