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
        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('maintenance_mode_enable_started');

        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(true);
        $trace->addTrace('maintenance_mode_enabled');

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
        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('maintenance_mode_disable_started');

        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);
        $trace->addTrace('maintenance_mode_disabled');

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }

    public function executeDelete($params)
    {
        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('maintenance_mode_disable_started');

        wp_umbrella_get_service('MaintenanceMode')->toggleMaintenanceMode(false);
        $trace->addTrace('maintenance_mode_disabled');

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }
}
