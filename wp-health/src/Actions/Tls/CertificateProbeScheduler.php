<?php
namespace WPUmbrella\Actions\Tls;

use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;

class CertificateProbeScheduler implements ExecuteHooks, DeactivationHook
{
    const ACTION_HOOK = 'wp_umbrella_tls_probe';

    const GROUP = 'umbrella_tls';

    const INTERVAL_SECONDS = 604800;

    const MAX_START_DELAY_SECONDS = 21600;

    public function hooks()
    {
        add_action('init', [$this, 'schedule'], 20);
        add_action(self::ACTION_HOOK, [$this, 'execute']);
    }

    public function schedule()
    {
        if (!function_exists('as_next_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (false !== as_next_scheduled_action(self::ACTION_HOOK, [], self::GROUP)) {
            return;
        }

        as_schedule_recurring_action(
            time() + mt_rand(0, self::MAX_START_DELAY_SECONDS),
            self::INTERVAL_SECONDS,
            self::ACTION_HOOK,
            [],
            self::GROUP
        );
    }

    public function execute()
    {
        $probe = wp_umbrella_get_service('CertificateProbe');

        if (!$probe) {
            return;
        }

        $probe->run();
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
