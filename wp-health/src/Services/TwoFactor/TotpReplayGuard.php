<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class TotpReplayGuard
{
    const META_KEY = 'wp_umbrella_2fa_last_counter';

    /**
     * @param int $userId
     * @param int $counter
     *
     * @return bool
     */
    public function accept($userId, $counter)
    {
        $userId = (int) $userId;
        $counter = (int) $counter;
        $last = get_user_meta($userId, self::META_KEY, true);

        if ($last === '' || $last === false || $last === null) {
            return (bool) add_user_meta($userId, self::META_KEY, $counter, true);
        }

        if ((int) $last >= $counter) {
            return false;
        }

        return (bool) update_user_meta($userId, self::META_KEY, $counter, $last);
    }

    /**
     * @param int $userId
     *
     * @return void
     */
    public function clear($userId)
    {
        delete_user_meta((int) $userId, self::META_KEY);
    }
}
