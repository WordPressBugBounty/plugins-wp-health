<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Snapshot extends AbstractController
{
    public function executeGet($params)
    {
        $data = wp_umbrella_get_service('Snapshot')->getData();

        return $this->returnResponse($data);
    }
}
