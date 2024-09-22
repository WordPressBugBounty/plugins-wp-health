<?php
namespace WPUmbrella\Actions\Queue\Scheduler;

use Exception;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Scheduler\AsyncQueueRunner;
use WPUmbrella\Core\Scheduler\QueueRunner;
use WPUmbrella\Services\Scheduler\ScheduleErrorCheck;
use WPUmbrella\Services\Scheduler\SchedulerLock;
use WPUmbrella\Core\Hooks\DeactivationHook;

class ErrorCheckQueueRunner implements ExecuteHooks, DeactivationHook
{
    use QueueRunner;
    use AsyncQueueRunner;

    const CRON_HOOK = 'wp_umbrella_error_check_run_queue';
    const CRON_SCHEDULE = 'every_fifteen_minutes';
    const LOCK_KEY = 'wp_umbrella_error_check_queue_runner';
    const INTERVAL = 15 * 60;

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
        $this->scheduler = wp_umbrella_get_service('ScheduleErrorCheck');
        $this->schedulerLock = wp_umbrella_get_service('SchedulerLock');
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @throws Exception
     */
    public function hooks()
    {
        add_filter('cron_schedules', [$this, 'addCronSchedules']);
        $backupVersion = get_option('wp_umbrella_backup_version');

        if ($backupVersion === 'v4') {
            return;
        }
        $this->cronHooks();
    }

    public function addCronSchedules($schedules)
    {
        $schedules[self::CRON_SCHEDULE] = [
            'interval' => self::INTERVAL,
            'display' => __('Every fifteen minutes'),
        ];

        return $schedules;
    }
}
