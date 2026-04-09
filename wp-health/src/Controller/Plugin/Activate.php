<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Activate extends AbstractController
{
    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$plugin) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $managePluginActivate = \wp_umbrella_get_service('PluginActivate');

        $trace = \wp_umbrella_get_service('RequestTrace');

        try {
            $trace->addTrace('activate_started', ['plugin' => $plugin]);
            $data = $managePluginActivate->activate($plugin);
            $trace->addTrace('activate_done', ['status' => $data['status'] ?? 'unknown']);

            if ($data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            $trace->addTrace('activate_exception', ['message' => $e->getMessage()]);
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
