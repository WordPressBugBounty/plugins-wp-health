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

        $response = wp_umbrella_get_service('UpgraderTempBackup')->deleteTempBackup([
            'slug' => dirname($plugin),
            'dir' => 'plugins'
        ]);

        return $this->returnResponse($response);
    }
}
