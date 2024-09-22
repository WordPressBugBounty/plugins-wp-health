<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Update extends AbstractController
{
    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$plugin) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $processFromWPUmbrella = true;

        $managePlugin = wp_umbrella_get_service('ManagePlugin');

        try {
            $data = $managePlugin->update($plugin);

            if (isset($data['status']) && $data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
