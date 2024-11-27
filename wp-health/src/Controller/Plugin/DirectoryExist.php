<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

class DirectoryExist extends AbstractController
{
    public function executeGet($params)
    {
        // Like "hello-world/hello-world.php"
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$plugin) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        $managePlugin = wp_umbrella_get_service('ManagePlugin');

        try {
            $version = $managePlugin->getVersionFromPluginFile($plugin);

            $data = $managePlugin->directoryPluginExist($plugin);

            return $this->returnResponse([
                'success' => $version !== false ? $data['success'] : false,
                'version' => $version
            ]);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
