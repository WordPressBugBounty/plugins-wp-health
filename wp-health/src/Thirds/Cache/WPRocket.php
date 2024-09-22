<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class WPRocket implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return function_exists('rocket_clean_domain');
    }

    public function clear()
    {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        $sitemap_preload = null;
        // Check if sitemap preload is enabled.
        if (function_exists('get_rocket_option')) {
            $sitemap_preload = get_rocket_option('sitemap_preload');
        }

        // Preload the cache.
        if (1 == $sitemap_preload) {
            if (function_exists('run_rocket_sitemap_preload')) {
                run_rocket_sitemap_preload();
            }
        }
    }
}
