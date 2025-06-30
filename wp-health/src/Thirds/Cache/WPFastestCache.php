<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class WPFastestCache implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('WpFastestCache');
    }

    public function clear()
    {
        if (!class_exists('WpFastestCache')) {
            return;
        }

        try {
            $wpfc = new \WpFastestCache();
            $wpfc->deleteCache();
        } catch (\Exception $e) {
        }
    }
}
