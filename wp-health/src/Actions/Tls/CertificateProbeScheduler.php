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

    const DEGRADED_INTERVAL_SECONDS = 86400;

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

        $next = as_next_scheduled_action(self::ACTION_HOOK, [], self::GROUP);

        if (false === $next) {
            as_schedule_recurring_action(
                time() + mt_rand(0, self::MAX_START_DELAY_SECONDS),
                self::INTERVAL_SECONDS,
                self::ACTION_HOOK,
                [],
                self::GROUP
            );

            return;
        }

        if (true === $next || $next <= time() + self::DEGRADED_INTERVAL_SECONDS || !function_exists('as_schedule_single_action')) {
            return;
        }

        $fallback = wp_umbrella_get_service('CertificateFallback');

        if (!$fallback || !$fallback->isActive()) {
            return;
        }

        as_schedule_single_action(time() + self::DEGRADED_INTERVAL_SECONDS, self::ACTION_HOOK, [], self::GROUP);
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
