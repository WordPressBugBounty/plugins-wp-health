<?php
namespace WPUmbrella\Controller\Plugin;

use Exception;
use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Manage\ManagePlugin;

if (!defined('ABSPATH')) {
    exit;
}

class Install extends AbstractController
{
    public function executePost($params)
    {
        $pluginUri = isset($params['plugin_uri']) ? $params['plugin_uri'] : null;

        if (!$pluginUri) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        /** @var ManagePlugin $managePlugin */
        $managePlugin = wp_umbrella_get_service('ManagePlugin');

        try {
            $data = $managePlugin->install($pluginUri);

            if ($data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
