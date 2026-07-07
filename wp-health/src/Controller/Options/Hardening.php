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
        ]);
    }

    public function executePost($params)
    {
        $settings = wp_umbrella_get_service('HardeningSettings')->updateSettings($params);

        return $this->returnResponse([
            'success' => true,
            'settings' => $settings,
        ]);
    }
}
