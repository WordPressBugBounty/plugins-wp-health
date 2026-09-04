<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class WPEngine implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('WpeCommon') && function_exists('wpe_param');
    }

    public function clear()
    {
        // The method has moved between releases of their mu-plugin.
        if (!method_exists('WpeCommon', 'purge_varnish_cache')) {
            return;
        }

        try {
            \WpeCommon::purge_varnish_cache();
        } catch (\Exception $e) {
        }

        do_action('wp_umbrella_wpengine_clear_cache');
    }
}
