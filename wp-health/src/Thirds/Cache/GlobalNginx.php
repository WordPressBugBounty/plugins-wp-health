<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class GlobalNginx implements CacheCollectionItem
{
    public static function isAvailable()
    {
        global $nginx_purger;

        if (!isset($nginx_purger)) {
            return false;
        }

        if (is_null($nginx_purger)) {
            return false;
        }

        return true;
    }

    public function clear()
    {
        try {
            global $nginx_purger;

            if (!isset($nginx_purger)) {
                return false;
            }

            if (is_null($nginx_purger)) {
                return;
            }

            $nginx_purger->purge_all();
        } catch (\Exception $e) {
        }
    }
}
