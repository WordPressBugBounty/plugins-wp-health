<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class StackCache implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('WPStackCache');
    }

    public function clear()
    {
        if (class_exists('WPStackCache')) {
            \WPStackCache::purge('all');
        }
    }
}
