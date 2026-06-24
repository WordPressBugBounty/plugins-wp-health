<?php
namespace WPUmbrella\Controller\User;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Suspend extends AbstractController
{
    public function executePost($params)
    {
        $userId = isset($params['user_id']) ? (int) $params['user_id'] : null;
        $suspend = isset($params['suspend']) ? filter_var($params['suspend'], FILTER_VALIDATE_BOOLEAN) : true;

        if (!$userId) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No user_id'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        $manageUser = \wp_umbrella_get_service('ManageUser');

        try {
            $data = $suspend ? $manageUser->suspend($userId) : $manageUser->unsuspend($userId);

            if ($data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
