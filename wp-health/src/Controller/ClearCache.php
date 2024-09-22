<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class ClearCache extends AbstractController
{
    public function executePost($params)
    {
        wp_umbrella_get_service('ClearCache')->clearCache();

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }
}
