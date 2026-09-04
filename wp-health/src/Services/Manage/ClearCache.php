<?php
namespace WPUmbrella\Services\Manage;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Collections\CacheCollection;
use WPUmbrella\Helpers\CacheCompatibility;

class ClearCache
{
    public function clearCache()
    {
        do_action('wp_umbrella_clear_cache');

        $collection = new CacheCollection();
        $applied = [];

        $items = CacheCompatibility::getCacheCompatibilities();
        foreach ($items as $item) {
            $available = $item::isAvailable();
            if (!$available) {
                continue;
            }

            $collection->addItem(
                new $item()
            );

            $applied[] = substr((string) strrchr($item, '\\'), 1);
        }

        // Which caches actually answered. Support has no other way to tell a
        // purge that found nothing to clear from one that ran.
        wp_umbrella_get_service('RequestTrace')->addTrace('cache_adapters', [
            'applied' => $applied,
        ]);

        if ($collection->isEmpty()) {
            return;
        }

        foreach ($collection->getIterator() as $item) {
            $item->clear();
        }
    }
}
