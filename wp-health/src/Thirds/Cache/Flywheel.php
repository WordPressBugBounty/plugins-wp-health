<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class Flywheel implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return class_exists('FlywheelNginxCompat');
    }

    public function clear()
    {
        wp_umbrella_get_service('Varnish')->purge();
        do_action('wp_umbrella_flywheel_clear_cache');
    }
}
