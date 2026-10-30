<?php
namespace WPUmbrella\Services\TwoFactor;

use WPUmbrella\Services\Hardening\TransientCounter;

if (!defined('ABSPATH')) {
    exit;
}

class OptionRowCounter extends TransientCounter
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

        return $this->incrementOptionRow($key, $window);
    }

    /**
     * @param string $key
     *
     * @return int
     */
    public function get($key)
    {
        $this->dropExpiredWindow($key);

        $count = get_option('_transient_' . $key);

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param string $key
     *
     * @return void
     */
    public function reset($key)
    {
        delete_option('_transient_' . $key);
        delete_option('_transient_timeout_' . $key);
    }

    /**
     * @param string $key
     *
     * @return void
     */
    protected function dropExpiredWindow($key)
    {
        $timeout = get_option('_transient_timeout_' . $key);

        if ($timeout === false || (int) $timeout >= time()) {
            return;
        }

        $this->reset($key);
    }
}
