<?php
namespace WPUmbrella\Actions\Queue\Scheduler;

use Exception;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Scheduler\AsyncQueueRunner;
use WPUmbrella\Core\Scheduler\QueueRunner;
use WPUmbrella\Services\Scheduler\ScheduleErrorCheck;
use WPUmbrella\Services\Scheduler\SchedulerLock;
use WPUmbrella\Core\Hooks\DeactivationHook;

class CleanTableRunner implements ExecuteHooks, DeactivationHook
{
    const CRON_HOOK = 'wp_umbrella_clean_table_run_queue';
    const CRON_SCHEDULE = 'daily';
    const LOCK_KEY = 'wp_umbrella_clean_table_queue_runner';
    const INTERVAL = 24 * HOUR_IN_SECONDS;

    public function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @throws Exception
     */
    public function hooks()
    {
        return;
    }
}
