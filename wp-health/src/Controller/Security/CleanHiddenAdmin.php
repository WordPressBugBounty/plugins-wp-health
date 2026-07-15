<?php
namespace WPUmbrella\Controller\Security;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class CleanHiddenAdmin extends AbstractController
{
    public function executePost($params)
    {
        $userId = isset($params['user_id']) ? (int) $params['user_id'] : 0;

        if ($userId <= 0) {
            return $this->returnResponse(['success' => false, 'code' => 'missing_parameters'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $result = wp_umbrella_get_service('ManageUser')->cleanOrphanCapabilities($userId);
        $success = isset($result['status']) && $result['status'] === 'success';

        return $this->returnResponse(
            array_merge(['success' => $success], $result),
            $success ? 200 : 409
        );
    }
}
