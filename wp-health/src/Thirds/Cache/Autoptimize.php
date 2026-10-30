<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class Autoptimize implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return defined('AUTOPTIMIZE_PLUGIN_VERSION') && method_exists('autoptimizeCache', 'clearall');
    }

    public function clear()
    {
        try {
            // Keep the default propagation: it flushes the page cache that still
            // references the deleted CSS/JS, including caches we have no adapter for.
            \autoptimizeCache::clearall();
        } catch (\Throwable $e) {
        }
    }
}
