<?php
namespace WPUmbrella\Actions\Queue\Scheduler;

use Exception;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Scheduler\AsyncQueueRunner;
use WPUmbrella\Core\Scheduler\QueueRunner;
use WPUmbrella\Services\Scheduler\ScheduleErrorCheck;
use WPUmbrella\Services\Scheduler\SchedulerLock;
use WPUmbrella\Services\Scheduler\SnapshotData;
use WPUmbrella\Core\Hooks\DeactivationHook;

class SnapshotDataRunner implements ExecuteHooks, DeactivationHook
{
    use QueueRunner;
    use AsyncQueueRunner;

    const CRON_HOOK = 'wp_umbrella_snapshot_data_run_queue';
    const CRON_SCHEDULE = 'hourly';
    const LOCK_KEY = 'wp_umbrella_snapshot_data_queue_runner';
    const INTERVAL = HOUR_IN_SECONDS;

    /**
     * @var ScheduleErrorCheck
     */
    protected $scheduler;

    /**
     * @var SchedulerLock
     */
    protected $schedulerLock;

    public function __construct()
    {
        $this->scheduler = wp_umbrella_get_service(SnapshotData::class);
        $this->schedulerLock = wp_umbrella_get_service(SchedulerLock::class);
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @deprecated
     */
    public function hooks()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
