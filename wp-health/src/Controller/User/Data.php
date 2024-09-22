<?php
namespace WPUmbrella\Controller\User;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Data extends AbstractController
{
    public function executeGet($params)
    {
        try {
            $number = isset($params['number']) ? $params['number'] : 10;
            $number = apply_filters('wp_umbrella_get_params_users_number', $number);

            $args = [
                'number' => $number,
                'offset' => isset($params['offset']) ? $params['offset'] : 0,
                'role' => isset($params['role']) ? $params['role'] : null,
            ];

            $users = wp_umbrella_get_service('UsersProvider')->get($args);

            return $this->returnResponse($users);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ], 403);
        }
    }
}
