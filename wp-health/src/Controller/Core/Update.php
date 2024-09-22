<?php
namespace WPUmbrella\Controller\Core;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Update extends AbstractController
{
    public function executePost($params)
    {
        try {
            $type = $params['type'] ?? 'classic';

            if ($type === 'core_upgrader') {
                $data = wp_umbrella_get_service('CoreUpdate')->upgradeByCoreUpgrader();
            } else {
                $data = wp_umbrella_get_service('CoreUpdate')->update();
            }

            if (!isset($data['status']) || $data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ], 403);
        }
    }
}
