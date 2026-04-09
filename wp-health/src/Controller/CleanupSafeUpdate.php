<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

class CleanupSafeUpdate extends AbstractController
{
    /**
     * We use GET method to set the backup version of the plugin because
     * too many hosts block POST or PUT requests unnecessarily.
     */
    public function executeGet($params)
    {
        // Like "hello-world/hello-world.php"
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$plugin) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'missing_parameters',
            ]);
        }

        $trace = wp_umbrella_get_service('RequestTrace');
        $pluginSlug = dirname($plugin);

        $trace->addTrace('cleanup_started', ['plugin' => $plugin]);

        $response = wp_umbrella_get_service('UpgraderTempBackup')->deleteTempBackup([
            'slug' => $pluginSlug,
            'dir' => 'plugins'
        ]);
        $trace->addTrace('temp_backup_deleted', ['success' => $response['success'] ?? false]);

        // Clean up the update state option as well
        wp_umbrella_get_service('UpdateStateManager')->deleteState($pluginSlug, 'plugin');
        $trace->addTrace('state_deleted');

        return $this->returnResponse($response);
    }
}
