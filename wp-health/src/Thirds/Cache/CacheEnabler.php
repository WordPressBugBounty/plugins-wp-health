<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class CacheEnabler implements CacheCollectionItem
{
    public static function isAvailable()
    {
        // The advanced-cache.php drop-in defines CACHE_ENABLER_VERSION and loads the
        // engine classes on its own. Only the plugin file registers the autoloader
        // that resolves Cache_Enabler.
        return class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_complete_cache');
    }

    public function clear()
    {
        try {
            do_action('cache_enabler_clear_complete_cache');
        } catch (\Throwable $e) {
        }
    }
}
