<?php
namespace WPUmbrella\Controller\Security;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Analysis extends AbstractController
{
    public function executePost($params)
    {
        $type = isset($params['type']) ? $params['type'] : null;

        switch ($type) {
            case 'hidden_admin':
                $result = wp_umbrella_get_service('HiddenAdminAnalyzer')->analyze();
                break;
            case 'htaccess_posture':
                $result = wp_umbrella_get_service('HtaccessPostureAnalyzer')->analyze();
                break;
            default:
                return $this->returnResponse([
                    'success' => false,
                    'code' => 'unknown_analysis_type',
                ], 400);
        }

        return $this->returnResponse([
            'success' => true,
            'type' => $type,
            'result' => $result,
        ]);
    }
}
