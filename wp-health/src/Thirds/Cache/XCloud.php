<?php
namespace WPUmbrella\Thirds\Cache;

use WPUmbrella\Core\Collections\CacheCollectionItem;

class XCloud implements CacheCollectionItem
{
    public static function isAvailable()
    {
        return self::hasXSpeed() || self::hasEdgePurge();
    }

    public function clear()
    {
        try {
            // Same entry point as the xSpeed "Purge all" button: page, object
            // and edge caches, xCloud's Cloudflare Enterprise included.
            if (self::hasXSpeed()) {
                \XSpeed\Purge_Runner::run(['all'], 'wp-umbrella');
                return;
            }

            if (self::hasEdgePurge()) {
                do_action('xcloud_cfe_purge_everything');
            }
        } catch (\Throwable $e) {
        }
    }

    protected static function hasXSpeed()
    {
        return class_exists('\XSpeed\Purge_Runner') && method_exists('\XSpeed\Purge_Runner', 'run');
    }

    // xCloud's mu-plugin defines the constant even when it failed to boot or
    // predates 1.3.0, so only the registered listener proves it takes purges.
    protected static function hasEdgePurge()
    {
        return defined('XCLOUD_CFE_PURGE_VERSION')
            && function_exists('has_action')
            && has_action('xcloud_cfe_purge_everything');
    }
}
