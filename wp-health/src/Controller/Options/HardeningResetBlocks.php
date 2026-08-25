<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class HardeningResetBlocks extends AbstractController
{
    public function executePost($params)
    {
        $result = wp_umbrella_get_service('ResetProtectionBlocks')->reset();

        return $this->returnResponse([
            'success' => true,
            'community_filter_cleared' => $result['community_filter_cleared'],
            'rate_limit_entries_cleared' => $result['rate_limit_entries_cleared'],
        ]);
    }
}
