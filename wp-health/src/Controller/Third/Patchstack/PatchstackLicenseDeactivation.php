<?php
namespace WPUmbrella\Controller\Third\Patchstack;

use WPUmbrella\Core\Models\AbstractController;

class PatchstackLicenseDeactivation extends AbstractController
{
    protected function deactivate($params)
    {
        if (!class_exists('Patchstack')) {
            return $this->returnResponse([
                'code' => 'error',
                'message' => 'Patchstack is not installed'
            ]);
        }

        $patchstack = \Patchstack::get_instance();
        $results = $patchstack->activation->deactivate();

        return $this->returnResponse([
            'code' => 'success',
            'data' => $results
        ]);
    }

    public function executePost($params)
    {
        return $this->deactivate($params);
    }

    public function executeGet($params)
    {
        return $this->deactivate($params);
    }
}
