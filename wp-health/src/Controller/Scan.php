<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

class Scan extends AbstractController
{
    public function executeGet($params)
    {
        try {
            $data = wp_umbrella_get_service('UmbrellaInformations')->getDiagnosticData();

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ], 403);
        }
    }
}
