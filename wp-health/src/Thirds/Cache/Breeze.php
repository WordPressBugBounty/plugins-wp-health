<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class Breeze implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return defined('BREEZE_VERSION');
    }

    public function clear()
    {
        try {
            do_action('breeze_clear_all_cache');
            do_action('breeze_clear_varnish');
        } catch (\Exception $e) {
        }
    }
}
