<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class Hummingbird implements CacheCollectionItem
{
    public static function isAvailable()
    {
        // The advanced-cache drop-in loads the page cache classes on its own,
        // and the main file defines WPHB_VERSION before bailing out on an
        // unsupported PHP. Only the main class proves the plugin booted.
        return defined('WPHB_VERSION') && class_exists('\Hummingbird\WP_Hummingbird');
    }

    public function clear()
    {
        try {
            do_action('wphb_clear_page_cache');
        } catch (\Throwable $e) {
        }
    }
}
