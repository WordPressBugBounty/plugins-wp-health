<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class NitroPack implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return defined('NITROPACK_VERSION');
    }

    public function clear()
    {

		if(!function_exists('nitropack_purge_cache')){
			return;
		}

		nitropack_purge_cache();
    }
}

