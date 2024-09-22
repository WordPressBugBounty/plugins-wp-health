<?php
namespace WPUmbrella\Controller\Core;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Data extends AbstractController
{
    public function executeGet($params)
    {
        try {
            $data = wp_umbrella_get_service('CoreProvider')->getCoreVersions();

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ], 403);
        }
    }
}
