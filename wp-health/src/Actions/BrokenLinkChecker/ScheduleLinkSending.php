<?php
namespace WPUmbrella\Actions\BrokenLinkChecker;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class ScheduleLinkSending implements ExecuteHooksBackend
{
    public function hooks()
    {
        if (!get_option('wp_umbrella_broken_link_checker_enabled')) {
            return;
        }

        add_action('admin_init', [$this, 'maybeScheduleSend']);
    }

    public function maybeScheduleSend()
    {
        if (!function_exists('as_next_scheduled_action')) {
            return;
        }

        if (as_next_scheduled_action('action_wp_umbrella_send_links') !== false) {
            return;
        }

        $repository = wp_umbrella_get_service('LinkRepository');

        $unsentThreshold = (defined('WP_UMBRELLA_DEBUG') && WP_UMBRELLA_DEBUG) ? 1 : 50;
        if ($repository->countUnsent() >= $unsentThreshold) {
            as_schedule_single_action(time(), 'action_wp_umbrella_send_links', [], 'umbrella_links');
        }
    }
}
