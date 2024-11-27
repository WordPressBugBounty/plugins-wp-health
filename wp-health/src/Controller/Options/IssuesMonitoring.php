<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class IssuesMonitoring extends AbstractController
{
    public function executePost($params)
    {
        $enable = isset($params['enable']) && $params['enable'] === 'true' ? true : false;

        update_option('wp_health_allow_tracking', $enable);

        return $this->returnResponse(['success' => true]);
    }
}
