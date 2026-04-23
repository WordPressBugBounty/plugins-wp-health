<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class CronCollector implements CollectorInterface
{
    public function getId()
    {
        return 'cron_health';
    }

    public function collect()
    {
        $cronArray = _get_cron_array();
        $totalEvents = 0;
        $overdueEvents = 0;
        $now = time();

        if (is_array($cronArray)) {
            foreach ($cronArray as $timestamp => $hooks) {
                foreach ($hooks as $hook => $events) {
                    $totalEvents += count($events);

                    if ($timestamp < $now) {
                        $overdueEvents += count($events);
                    }
                }
            }
        }

        return [
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'total_events' => $totalEvents,
            'overdue_events' => $overdueEvents,
        ];
    }
}
