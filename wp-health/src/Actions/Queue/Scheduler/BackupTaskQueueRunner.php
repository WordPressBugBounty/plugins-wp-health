<?php
namespace WPUmbrella\Actions\Queue\Scheduler;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Scheduler\AsyncQueueRunner;
use WPUmbrella\Core\Scheduler\QueueRunner;
use WPUmbrella\Services\Scheduler\SchedulerLock;
use WPUmbrella\Services\Scheduler\ScheduleTaskBackup;
use WPUmbrella\Core\Hooks\DeactivationHook;

class BackupTaskQueueRunner implements ExecuteHooks, DeactivationHook
{
    use QueueRunner;
    use AsyncQueueRunner;

    const CRON_HOOK = 'wp_umbrella_task_backup_run_queue';
    const CRON_SCHEDULE = 'every_minute';
    const LOCK_KEY = 'wp_umbrella_task_backup_queue_runner';
    const INTERVAL = 60;

    public function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function hooks()
    {
        return;
    }

    public function addCronSchedules($schedules)
    {
        $schedules[self::CRON_SCHEDULE] = [
            'interval' => self::INTERVAL,
            'display' => __('Every minute'),
        ];

        return $schedules;
    }
}
