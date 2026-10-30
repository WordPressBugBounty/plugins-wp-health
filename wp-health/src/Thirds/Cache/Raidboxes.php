<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class Raidboxes implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('RaidboxesNginxCacheFunctions');
    }

    public function clear()
    {
        if (!method_exists('RaidboxesNginxCacheFunctions', 'purge_cache')) {
            return;
        }

        try {
            (new \RaidboxesNginxCacheFunctions())->purge_cache();
        } catch (\Throwable $e) {
        }
    }
}
