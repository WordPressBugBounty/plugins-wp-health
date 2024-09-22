<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivate extends AbstractController
{
    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$plugin) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $managePluginDeactivate = \wp_umbrella_get_service('PluginDeactivate');

        try {
            $data = $managePluginDeactivate->deactivate($plugin);

            if ($data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
