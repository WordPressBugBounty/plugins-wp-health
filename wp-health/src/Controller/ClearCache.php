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
        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('clear_cache_started');

        wp_umbrella_get_service('ClearCache')->clearCache();
        $trace->addTrace('clear_cache_done');

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }
}
