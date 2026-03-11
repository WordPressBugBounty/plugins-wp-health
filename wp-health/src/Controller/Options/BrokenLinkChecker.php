<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class BrokenLinkChecker extends AbstractController
{
    public function executePost($params)
    {
        $enable = isset($params['enable']) && $params['enable'] === 'true' ? true : false;

        update_option('wp_umbrella_broken_link_checker_enabled', $enable);

        if (isset($params['scan_interval_hours'])) {
            $scanInterval = (int) $params['scan_interval_hours'];
            if ($scanInterval > 0) {
                update_option('wp_umbrella_blc_scan_interval', $scanInterval);
            }
        }

        return $this->returnResponse(['success' => true]);
    }
}
