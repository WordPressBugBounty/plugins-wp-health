<?php
namespace WPUmbrella\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class CacheCompatibility
{
    public static function getCacheCompatibilities()
    {
        return apply_filters('wp_umbrella_cache_compatibilities', [
            "\WPUmbrella\Thirds\Cache\NitroPack",
            "\WPUmbrella\Thirds\Cache\WPRocket",
            "\WPUmbrella\Thirds\Cache\Kinsta",
            "\WPUmbrella\Thirds\Cache\Flywheel",
            "\WPUmbrella\Thirds\Cache\WPServer",
            "\WPUmbrella\Thirds\Cache\Breeze",
            "\WPUmbrella\Thirds\Cache\GlobalNginx",
            "\WPUmbrella\Thirds\Cache\LiteSpeed",
            "\WPUmbrella\Thirds\Cache\WPFastestCache",
            "\WPUmbrella\Thirds\Cache\WPSuperCache",
        ]);
    }
}
