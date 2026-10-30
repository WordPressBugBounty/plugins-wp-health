<?php
namespace WPUmbrella\Thirds\Cache;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Core\Collections\CacheCollectionItem;

class W3TotalCache implements CacheCollectionItem
{
    public static function isAvailable()
    {
        // The W3TC drop-ins load its API (W3TC, w3tc_flush_all) on their own,
        // even with the plugin deactivated. Only the root loader proves it booted.
        return isset($GLOBALS['w3tc_root'])
            && $GLOBALS['w3tc_root'] instanceof \W3TC\Root_Loader
            && function_exists('w3tc_flush_all');
    }

    public function clear()
    {
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
    }
}
