<?php
namespace WPUmbrella\Services\Loopback;

if (!defined('ABSPATH')) {
    exit;
}

class LoopbackToken
{
    const NAME_SERVICE = 'LoopbackToken';

    const OPTION_PREFIX = 'wpu_lbt_';

    const RETENTION_SECONDS = 600;

    const CONSUMED = 'consumed';

    const REPLAYED = 'replayed';

    const UNKNOWN = 'unknown';

    const EXPIRED = 'expired';

    const MISMATCH = 'mismatch';

    const LIFETIME_SECONDS = 300;

    /**
     * @param string $kind
     * @param string $timestamp
     * @return string
     */
    public function issue($kind, $timestamp)
    {
        global $wpdb;

        $token = wp_generate_password(32, false);

        $this->purge();

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                self::OPTION_PREFIX . $token,
                time() . '|' . (string) $kind . '|' . (string) $timestamp
            )
        );

        return $token;
    }

    /**
     * @param string $token
     * @param string $kind
     * @param string $timestamp
     * @return string One of CONSUMED, REPLAYED, EXPIRED, MISMATCH or UNKNOWN
     */
    public function consume($token, $kind, $timestamp)
    {
        global $wpdb;

        if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return self::UNKNOWN;
        }

        $name = self::OPTION_PREFIX . $token;

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name)
        );

        if (!is_string($value)) {
            return self::UNKNOWN;
        }

        if (substr($value, 0, 1) === '-') {
            return self::REPLAYED;
        }

        $parts = explode('|', $value);

        if (count($parts) !== 3 || !ctype_digit($parts[0]) || $parts[1] !== (string) $kind || $parts[2] !== (string) $timestamp) {
            return self::MISMATCH;
        }

        if (time() - (int) $parts[0] > self::LIFETIME_SECONDS) {
            return self::EXPIRED;
        }

        $consumed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                (string) -time(),
                $name,
                $value
            )
        );

        return $consumed === 1 ? self::CONSUMED : self::REPLAYED;
    }

    protected function purge()
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND ABS(CAST(SUBSTRING_INDEX(option_value, '|', 1) AS SIGNED)) < %d",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%',
                time() - self::RETENTION_SECONDS
            )
        );
    }
}
