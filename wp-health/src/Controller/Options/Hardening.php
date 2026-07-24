<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Hardening extends AbstractController
{
    public function executeGet($params)
    {
        $states = wp_umbrella_get_service('HardeningSettings')->getStates();

        return $this->returnResponse([
            'success' => true,
            'settings' => $states,
            'web_server' => wp_umbrella_get_service('WebServer')->getType(),
        ]);
    }

    public function executePost($params)
    {
        $service = wp_umbrella_get_service('HardeningSettings');
        $settings = $service->updateSettings($params);

        return $this->returnResponse([
            'success' => true,
            'settings' => $settings,
            'web_server' => wp_umbrella_get_service('WebServer')->getType(),
            'htaccess_result' => $service->getLastHtaccessResult(),
        ]);
    }
}
