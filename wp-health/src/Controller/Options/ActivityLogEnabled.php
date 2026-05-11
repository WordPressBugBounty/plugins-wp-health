<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Models\AbstractController;

/**
 * Toggles the `wp_umbrella_activity_log_enabled` option from the WP Umbrella
 * dashboard. The option gates the whole activity log framework (sensors,
 * buffer, sync), so flipping it off is the kill switch a user can use without
 * uninstalling the plugin.
 */
class ActivityLogEnabled extends AbstractController
{
    public function executePost($params)
    {
        $enable = isset($params['enable']) && $params['enable'] === 'true' ? true : false;

        update_option('wp_umbrella_activity_log_enabled', $enable);

        $scheduler = new SyncScheduler();
        $enable ? $scheduler->schedule() : $scheduler->unschedule();

        return $this->returnResponse(['success' => true]);
    }
}
