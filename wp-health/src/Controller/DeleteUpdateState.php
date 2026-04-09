<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

class DeleteUpdateState extends AbstractController
{
    /**
     * Force-clean all update states.
     * Called by the worker after an update process completes to ensure no orphaned states remain.
     */
    public function executeGet($params)
    {
        $stateManager = wp_umbrella_get_service('UpdateStateManager');
        $trace = wp_umbrella_get_service('RequestTrace');

        $trace->addTrace('delete_update_state_started');

        $stateManager->cleanupStaleStates(0);

        $trace->addTrace('delete_update_state_done');

        return $this->returnResponse([
            'success' => true,
            'message' => 'All update states cleaned',
        ]);
    }
}
