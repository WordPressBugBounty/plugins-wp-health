<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class UpdateMultiple extends AbstractController
{
    public function executePost($params)
    {
        $plugins = isset($params['plugins']) ? $params['plugins'] : null;

        if (!$plugins) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $managePlugin = wp_umbrella_get_service('ManagePlugin');

        $onlyAjax = isset($params['only_ajax']) ? $params['only_ajax'] : false;
        $safeUpdate = isset($params['safe_update']) ? $params['safe_update'] : false;

        try {
            $data = $managePlugin->bulkUpdate($plugins, [
                'only_ajax' => $onlyAjax,
                'safe_update' => isset($params['safe_update']) ? $params['safe_update'] : false
            ]);

            if (isset($data['status']) && $data['status'] === 'error') {
                $this->returnResponse($data, 403);
                return;
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
