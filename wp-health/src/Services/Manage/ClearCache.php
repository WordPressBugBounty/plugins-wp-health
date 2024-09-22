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
        $collection = new CacheCollection();

        $items = CacheCompatibility::getCacheCompatibilities();
        foreach ($items as $item) {
            $available = $item::isAvailable();
            if (!$available) {
                continue;
            }

            $collection->addItem(
                new $item()
            );
        }

        if ($collection->isEmpty()) {
            return;
        }

        foreach ($collection->getIterator() as $item) {
            $item->clear();
        }
    }
}
