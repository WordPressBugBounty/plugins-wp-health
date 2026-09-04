<?php
namespace WPUmbrella\Services\Hardening;

if (!defined('ABSPATH')) {
    exit;
}

class TransientCounter
{
    /**
     * @param string $key
     * @param int    $window
     *
     * @return int
     */
    public function increment($key, $window)
    {
        $this->dropExpiredWindow($key);

        if (wp_using_ext_object_cache()) {
            if (wp_cache_add($key, 1, 'transient', $window)) {
                return 1;
            }

            $count = wp_cache_incr($key, 1, 'transient');

            return is_numeric($count) ? (int) $count : $this->get($key);
        }

        return $this->incrementOptionRow($key, $window);
    }

    /**
     * @param string $key
     *
     * @return int
     */
    public function get($key)
    {
        $count = get_transient($key);

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * A read is what makes WordPress notice a window whose timeout has passed
     * and delete both rows, so the next count starts a fresh window instead of
     * adding to a stale one. The return value is of no use here.
     *
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
     * @param int    $window
     *
     * @return int
     */
    protected function incrementOptionRow($key, $window)
    {
        global $wpdb;

        $option = '_transient_' . $key;
        $timeout = '_transient_timeout_' . $key;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
                $timeout,
                (string) (time() + $window)
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off')"
                . ' ON DUPLICATE KEY UPDATE option_value = option_value + 1',
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
}
