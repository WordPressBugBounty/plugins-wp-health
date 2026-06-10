<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class RedisObjectCache implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public function clear()
    {
        try {
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        } catch (\Exception $e) {
        }
    }
}
