<?php
namespace WPUmbrella\Controller\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Models\AbstractController;

class RepairController extends AbstractController
{
    public function executePost($params)
    {
        $result = wp_umbrella_get_service('PairingService')->runPairing();

        return $this->returnResponse([
            'code' => $result ? 'success' : 'noop',
        ]);
    }

    public function executeGet($params)
    {
        return $this->executePost($params);
    }
}
