<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class SiteGround implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return function_exists('sg_cachepress_purge_cache');
    }

    public function clear()
    {
        try {
            sg_cachepress_purge_cache();

            if (class_exists('\SiteGround_Optimizer\Supercacher\Supercacher')) {
                \SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
            }
        } catch (\Exception $e) {
        }
    }
}
