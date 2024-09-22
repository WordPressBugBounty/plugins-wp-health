<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Rollback extends AbstractController
{
    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;
        $version = isset($params['version']) ? $params['version'] : null;

        if (!$plugin || !$version) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $managePlugin = wp_umbrella_get_service('ManagePlugin');

        try {
            $data = wp_umbrella_get_service('ManagePlugin')->rollback($plugin, [
                'version' => $version,
            ]);

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
