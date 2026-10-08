<?php
namespace WPUmbrella\Services\Loopback;

if (!defined('ABSPATH')) {
    exit;
}

class LoopbackStats
{
    const NAME_SERVICE = 'LoopbackStats';

    const OPTION = 'wp_umbrella_loopback_stats';

    /**
     * @param string $kind
     * @param string[] $events
     * @param string $strippedKeys
     */
    public function record($kind, array $events, $strippedKeys = '')
    {
        $stats = $this->getData();
        $now = time();

        if (!isset($stats['since'])) {
            $stats['since'] = $now;
        }

        $counters = isset($stats[$kind]) && is_array($stats[$kind]) ? $stats[$kind] : [];

        foreach ($events as $event) {
            $counters[$event] = (isset($counters[$event]) ? (int) $counters[$event] : 0) + 1;
            $counters['last_' . $event . '_at'] = $now;
        }

        if ($strippedKeys !== '') {
            $counters['last_stripped_keys'] = $strippedKeys;
        }

        $stats[$kind] = $counters;

        update_option(self::OPTION, $stats, false);
    }

    /**
     * @return array
     */
    public function getData()
    {
        $stats = get_option(self::OPTION, []);

        return is_array($stats) ? $stats : [];
    }
}
