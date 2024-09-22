<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class FlyingPress implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('FlyingPress\Purge') || class_exists('\\FlyingPress\\Purge');
    }

    public function clear()
    {
        try {
			\FlyingPress\Purge::purge_everything();
			\FlyingPress\Preload::preload_cache();
		} catch (\Exception $e) {

		}
    }
}
