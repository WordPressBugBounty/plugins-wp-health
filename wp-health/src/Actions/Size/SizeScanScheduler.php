<?php
namespace WPUmbrella\Actions\Size;

use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

class SizeScanScheduler implements ExecuteHooks, DeactivationHook
{
    const ACTION_HOOK = 'wp_umbrella_wordpress_sizes_scan';

    const GROUP = 'umbrella_sizes';

    const MAX_START_DELAY_SECONDS = 60;

    public function hooks()
    {
        add_action(self::ACTION_HOOK, [$this, 'execute']);
    }

    /**
     * Nothing is scheduled on init: the scan only runs when the size route has
     * been asked for a value it does not hold, so a site nobody watches never
     * walks its own filesystem.
     */
    public function schedule()
    {
        if (!function_exists('as_next_scheduled_action') || !function_exists('as_schedule_single_action')) {
            return;
        }

        if (false !== as_next_scheduled_action(self::ACTION_HOOK, [], self::GROUP)) {
            return;
        }

        as_schedule_single_action(
            time() + mt_rand(0, self::MAX_START_DELAY_SECONDS),
            self::ACTION_HOOK,
            [],
            self::GROUP
        );
    }

    public function execute()
    {
        $store = wp_umbrella_get_service('WordPressSizeStore');

        if (!$store) {
            return;
        }

        $store->refresh();
    }

    public function unschedule()
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }

        as_unschedule_all_actions(self::ACTION_HOOK, [], self::GROUP);
    }

    public function deactivate()
    {
        $this->unschedule();
    }
}
