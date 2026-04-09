<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

class CleanupThemeSafeUpdate extends AbstractController
{
    /**
     * We use GET method to set the backup version of the plugin because
     * too many hosts block POST or PUT requests unnecessarily.
     */
    public function executeGet($params)
    {
        // Like "twentytwentyfour"
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$theme) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'missing_parameters',
            ]);
        }

        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('cleanup_started', ['theme' => $theme]);

        $response = wp_umbrella_get_service('UpgraderTempBackup')->deleteTempBackup([
            'slug' => $theme,
            'dir' => 'themes'
        ]);
        $trace->addTrace('temp_backup_deleted', ['success' => $response['success'] ?? false]);

        // Clean up the update state option as well
        wp_umbrella_get_service('UpdateStateManager')->deleteState($theme, 'theme');
        $trace->addTrace('state_deleted');

        return $this->returnResponse($response);
    }
}
