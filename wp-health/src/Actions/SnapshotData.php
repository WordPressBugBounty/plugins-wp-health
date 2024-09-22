<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Hooks\DeactivationHook;

/**
 * @deprecated
 */
class SnapshotData implements DeactivationHook
{
    public function deactivate()
    {
        as_unschedule_action('wp_umbrella_snapshot_data');
    }
}
