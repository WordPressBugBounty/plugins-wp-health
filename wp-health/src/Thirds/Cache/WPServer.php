<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class WPServer implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return (defined('DB_HOST') && strpos(wp_umbrella_get_service('WordPressContext')->getDbHost(), '.wpserveur.net') !== false);
    }

    public function clear()
    {
        wp_umbrella_get_service('Varnish')->purge();
        do_action('wp_umbrella_wp_server_clear_cache');
    }
}
