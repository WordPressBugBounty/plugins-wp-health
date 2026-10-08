<?php
namespace WPUmbrella\Controller\Network;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Data extends AbstractController
{
    public function executeGet($params)
    {
        if (!is_multisite()) {
            return $this->returnResponse(['code' => 'not_multisite', 'message' => 'Not a multisite'], 404);
        }

        try {
            $page = isset($params['page']) ? (int) $params['page'] : 1;

            return $this->returnResponse(wp_umbrella_get_service('NetworkProvider')->getData($page));
        } catch (\Throwable $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => 'Unable to retrieve the network data'
            ]);
        }
    }
}
