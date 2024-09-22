<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Logs extends AbstractController
{
    public function executeGet($params)
    {
		$data = wp_umbrella_get_service('Logger')->getLogs();

        return $this->returnResponse($data);
    }
}
