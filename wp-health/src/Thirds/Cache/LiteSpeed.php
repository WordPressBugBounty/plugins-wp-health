<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class LiteSpeed implements CacheCollectionItem
{
    public static function isAvailable()
    {
        // The object cache drop-in loads the LiteSpeed classes on its own, even
        // with the plugin deactivated. Only LSCWP_V proves the plugin booted.
        return defined('LSCWP_V') && class_exists('\LiteSpeed\Purge');
    }

    public function clear()
    {
        try {
            do_action('litespeed_purge_all');
        } catch (\Throwable $e) {
        }
    }
}
