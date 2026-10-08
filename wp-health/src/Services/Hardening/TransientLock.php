<?php
namespace WPUmbrella\Services\Hardening;

if (!defined('ABSPATH')) {
    exit;
}

class TransientLock
{
    /**
     * @param string $key
     * @param int    $ttl
     *
     * @return bool true for the one caller that took the lock
     */
    public function acquire($key, $ttl)
    {
        $claim = (time() + $ttl) . ':' . bin2hex(random_bytes(4));

        if (wp_using_ext_object_cache()) {
            return (bool) wp_cache_add($key, $claim, 'transient', $ttl);
        }

        return $this->claimOptionRow($key, $claim, $ttl);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isHeld($key)
    {
        return $this->expiresAt(get_transient($key)) > time();
    }

    /**
     * @param string $key
     *
     * @return void
     */
    public function release($key)
    {
        delete_transient($key);
    }

    /**
     * @param string $key
     * @param string $claim
     * @param int    $ttl
     *
     * @return bool
     */
    protected function claimOptionRow($key, $claim, $ttl)
    {
        global $wpdb;

        $option = '_transient_' . $key;
        $timeout = '_transient_timeout_' . $key;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')"
                . " ON DUPLICATE KEY UPDATE option_value = IF(CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d, %s, option_value)",
                $option,
                $claim,
                time(),
                $claim
            )
        );

        $held = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option)
        );

        wp_cache_delete($option, 'options');

        if ($held !== $claim) {
            return false;
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')"
                . ' ON DUPLICATE KEY UPDATE option_value = %s',
                $timeout,
                (string) (time() + $ttl),
                (string) (time() + $ttl)
            )
        );

        wp_cache_delete($timeout, 'options');
        wp_cache_delete('notoptions', 'options');

        return true;
    }

    /**
     * @param mixed $claim
     *
     * @return int
     */
    protected function expiresAt($claim)
    {
        return is_string($claim) || is_int($claim) ? (int) $claim : 0;
    }
}
