<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class WPOptimize implements CacheCollectionItem
{
    public static function isAvailable()
    {
        // The advanced-cache.php drop-in defines wpo_cache_flush() on its own.
        // Only WPO_VERSION proves the plugin booted.
        if (!defined('WPO_VERSION') || !function_exists('wpo_cache_flush') || !function_exists('WP_Optimize')) {
            return false;
        }

        $optimize = WP_Optimize();
        if (!method_exists($optimize, 'get_page_cache')) {
            return false;
        }

        // Many sites only use WP-Optimize to clean the database. wpo_cache_flush()
        // also flushes the object cache, so stay off when page caching is off.
        $pageCache = $optimize->get_page_cache();

        return is_object($pageCache) && method_exists($pageCache, 'is_enabled') && $pageCache->is_enabled();
    }

    public function clear()
    {
        try {
            if (function_exists('wpo_cache_flush')) {
                wpo_cache_flush();
            }
        } catch (\Throwable $e) {
        }
    }
}
