<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

class CanUpdate extends AbstractController
{
    /**
     * Check whether the site is available for a new update.
     * The worker calls this before launching any plugin/theme update process.
     */
    public function executeGet($params)
    {
        $stateManager = wp_umbrella_get_service('UpdateStateManager');
        $trace = wp_umbrella_get_service('RequestTrace');

        $hasActiveUpdates = $stateManager->hasActiveUpdates();

        $trace->addTrace('can_update_check', ['can_update' => !$hasActiveUpdates]);

        return $this->returnResponse([
            'success' => true,
            'can_update' => !$hasActiveUpdates,
        ]);
    }
}
