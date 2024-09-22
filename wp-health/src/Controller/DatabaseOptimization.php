<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\UmbrellaRequest;

class DatabaseOptimization extends AbstractController
{
    public function executePost($params)
    {
        $type = $params['type'] ?? null;
        if (is_null($type)) {
            return $this->returnResponse([
                'code' => 'missing_parameters'
            ], 401);
        }

        $data = wp_umbrella_get_service('DatabaseOptimizationManager')->optimizeByType($type);

        return $this->returnResponse($data);
    }

    public function executeGet($params)
    {
        $type = $params['type'] ?? null;

        if (!is_null($type)) {
            $data = wp_umbrella_get_service('DatabaseOptimizationManager')->getDataByType($type);
            return $this->returnResponse([
                $type => $data
            ]);
        }

        $data = wp_umbrella_get_service('DatabaseOptimizationManager')->getData();
        return $this->returnResponse($data);
    }
}
