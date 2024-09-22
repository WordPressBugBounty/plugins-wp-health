<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Delete extends AbstractController
{
    protected function deletePlugin($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        $managePlugin = \wp_umbrella_get_service('ManagePlugin');

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        try {
            $data = $managePlugin->delete($plugin);

            if ($data['status'] === 'error') {
                return $this->returnResponse($data, 500);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function executeDelete($params)
    {
        return $this->deletePlugin($params);
    }

    public function executeGet($params)
    {
        return $this->deletePlugin($params);
    }
}
