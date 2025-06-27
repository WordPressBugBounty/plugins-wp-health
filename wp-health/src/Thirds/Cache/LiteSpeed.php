<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class LiteSpeed implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('\LiteSpeed\Purge') || class_exists('\LiteSpeed\Purge');
    }

    public function clear()
    {
        try {
            \LiteSpeed\Purge::purge_all();
        } catch (\Exception $e) {
        }
    }
}
