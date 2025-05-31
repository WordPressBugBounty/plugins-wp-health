<?php
namespace WPUmbrella\Controller\Third\Patchstack;

use WPUmbrella\Core\Models\AbstractController;

class PatchstackLicenseActivation extends AbstractController
{
    protected function activate($params)
    {
        $clientId = isset($params['clientId']) ? $params['clientId'] : null;
        $licenseKey = isset($params['secret']) ? $params['secret'] : null;

        if (!$clientId || !$licenseKey) {
            return $this->returnResponse([
                'code' => 'error',
                'message' => 'Client ID and license key are required'
            ]);
        }

        if (!class_exists('Patchstack')) {
            return $this->returnResponse([
                'code' => 'error',
                'message' => 'Patchstack is not installed'
            ]);
        }

        $patchstack = \Patchstack::get_instance();
        $results = $patchstack->activation->alter_license($clientId, $licenseKey, 'activate');

        if ($results) {
            $response = $patchstack->api->update_license_status();

            return $this->returnResponse([
                'code' => 'success',
                'data' => $response
            ]);
        }

        return $this->returnResponse([
            'code' => 'error',
            'data' => $response
        ]);
    }

    public function executePost($params)
    {
        return $this->activate($params);
    }

    public function executeGet($params)
    {
        return $this->activate($params);
    }
}
