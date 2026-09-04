<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Actions\Admin\PrepareErrorHandler;
use WPUmbrella\Core\Models\AbstractController;

class IssuesMonitoring extends AbstractController
{
    public function executePost($params)
    {
        $enable = isset($params['enable']) && $params['enable'] === 'true' ? true : false;

        (new PrepareErrorHandler())->updateTracking($enable);

        return $this->returnResponse(['success' => true]);
    }
}
