<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class Kinsta implements CacheCollectionItem
{
    public static function isAvailable()
    {
        global $kinsta_cache;
        return isset($kinsta_cache) && class_exists('\\Kinsta\\CDN_Enabler');
    }

    public function clear()
    {
        global $kinsta_cache;

        try {
            if (!empty($kinsta_cache->kinsta_cache_purge)) {
                $kinsta_cache->kinsta_cache_purge->purge_complete_caches();
            }
        } catch (\Exception $e) {
        }
    }
}
