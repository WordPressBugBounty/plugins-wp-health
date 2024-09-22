<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class DataSingle extends AbstractController
{
    public function executeGet($params)
    {
        try {

			$plugin = isset($params['plugin']) ? $params['plugin'] : null;

			if (!$plugin) {
				return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
			}

			$plugin = wp_umbrella_get_service('PluginsProvider')->getPlugin($params['plugin']);

            wp_umbrella_get_service('ManagePlugin')->clearUpdates();
            return $this->returnResponse($plugin);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
