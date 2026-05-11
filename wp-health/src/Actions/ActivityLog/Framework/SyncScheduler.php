<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Schedules the recurring Action Scheduler job that drains the activity log
 * buffer and POSTs events to the WP Umbrella API.
 *
 * The scheduling is delayed by a small random offset (0 to 60 seconds) so
 * that thousands of plugin installs do not all hit the API at the same
 * second of the same minute.
 *
 * The interval is configurable from the plugin support page and stored in
 * the `wp_umbrella_activity_log_sync_interval_seconds` option. The default
 * is 5 minutes; the floor is 60 seconds to avoid hammering the API.
 */
class SyncScheduler
{
    const ACTION_HOOK = 'wp_umbrella_activity_log_sync';
    const GROUP = 'wp-umbrella';
    const OPTION_INTERVAL = 'wp_umbrella_activity_log_sync_interval_seconds';
    const DEFAULT_INTERVAL_SECONDS = 300;
    const MIN_INTERVAL_SECONDS = 60;
    const MAX_INTERVAL_SECONDS = 3600;

    /**
     * Schedules the recurring sync if it is not already scheduled.
     *
     * @return void
     */
    public function schedule()
    {
        if (!function_exists('as_next_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (false !== as_next_scheduled_action(self::ACTION_HOOK, [], self::GROUP)) {
            return;
        }

        $jitter = mt_rand(0, 60);

        as_schedule_recurring_action(
            time() + $jitter,
            self::resolveInterval(),
            self::ACTION_HOOK,
            [],
            self::GROUP
        );
    }

    /**
     * Cancels the recurring sync. Used on plugin deactivation, when the
     * activity log toggle is turned off, or when the interval changes and
     * a fresh schedule is required.
     *
     * @return void
     */
    public function unschedule()
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }

        as_unschedule_all_actions(self::ACTION_HOOK, [], self::GROUP);
    }

    /**
     * Resolves the configured interval, clamped between the hard min and
     * max bounds. Falls back to the default when the option is missing or
     * non numeric.
     *
     * @return int
     */
    public static function resolveInterval()
    {
        $stored = get_option(self::OPTION_INTERVAL, self::DEFAULT_INTERVAL_SECONDS);
        $seconds = is_numeric($stored) ? (int) $stored : self::DEFAULT_INTERVAL_SECONDS;

        if ($seconds < self::MIN_INTERVAL_SECONDS) {
            return self::MIN_INTERVAL_SECONDS;
        }

        if ($seconds > self::MAX_INTERVAL_SECONDS) {
            return self::MAX_INTERVAL_SECONDS;
        }

        return $seconds;
    }
}
