<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Helpers\GodTransient;

class TrackingError implements ExecuteHooksBackend
{
    public function hooks()
    {
        add_action('admin_init', [$this, 'init']);
    }

    public function init()
    {
        $data = get_transient(GodTransient::ERRORS_SAVE);

        if (!$data || empty($data)) {
            return;
        }

        delete_transient(GodTransient::ERRORS_SAVE);

        update_option(GodTransient::ERRORS_SAVE, $data, false);

        as_schedule_single_action(time(), 'action_wp_umbrella_send_errors_v2', [], 'umbrella_errors');
    }
}
