<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Directory;

class Tables extends AbstractController
{
    public function executeGet($params)
    {
        $data = wp_umbrella_get_service('DatabaseTablesProvider')->getTablesSize();

        return $this->returnResponse($data);
    }
}
