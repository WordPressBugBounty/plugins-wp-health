<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class MaintenanceMode extends AbstractController
{
    public function executePost($params)
    {
        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(true);

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }

    /**
     * Use by /delete-maintenance
     * Some host don't like DELETE method
     */
    public function executeGet($params)
    {
        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }

    public function executeDelete($params)
    {
        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }
}
