<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class WPSuperCache implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return defined('WPSC_VERSION_ID') && function_exists('wp_cache_clean_cache');
    }

    public function clear()
    {
        if (!function_exists('wp_cache_clean_cache')) {
            return;
        }

        try {
            global $file_prefix;

            wp_cache_clean_cache($file_prefix, true);
        } catch (\Exception $e) {
        }
    }
}
