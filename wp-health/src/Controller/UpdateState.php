<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

class UpdateState extends AbstractController
{
    /**
     * Returns the current update state for a plugin or theme.
     * The worker reads this before deciding whether to rollback.
     */
    public function executeGet($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$plugin && !$theme) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'missing_parameters',
                'message' => 'Either plugin or theme parameter is required',
            ], 400);
        }

        $stateManager = wp_umbrella_get_service('UpdateStateManager');

        // Opportunistic cleanup: the worker is the main consumer of update states,
        // so piggyback on its reads to garbage-collect orphaned options.
        $stateManager->cleanupStaleStates();

        if ($plugin) {
            $slug = dirname($plugin);
            $state = $stateManager->getState($slug, 'plugin');
        } else {
            $state = $stateManager->getState($theme, 'theme');
        }

        if ($state === null) {
            return $this->returnResponse([
                'success' => true,
                'state' => null,
                'message' => 'No update state found',
            ]);
        }

        return $this->returnResponse([
            'success' => true,
            'state' => $state,
        ]);
    }

}
