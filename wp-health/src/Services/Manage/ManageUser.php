<?php
namespace WPUmbrella\Services\Manage;

if (!defined('ABSPATH')) {
    exit;
}

class ManageUser
{
    const NAME_SERVICE = 'ManageUser';

    const META_KEY = 'wp_umbrella_suspended';

    public function suspend($userId)
    {
        $userId = (int) $userId;

        $user = get_userdata($userId);

        if (!$user) {
            return [
                'status' => 'error',
                'code' => 'user_not_exist',
            ];
        }

        if ($userId === get_current_user_id()) {
            return [
                'status' => 'error',
                'code' => 'cannot_suspend_current_user',
            ];
        }

        if ($this->isLastAdministrator($user)) {
            return [
                'status' => 'error',
                'code' => 'cannot_suspend_last_administrator',
            ];
        }

        update_user_meta($userId, self::META_KEY, 1);

        return [
            'status' => 'success',
            'code' => 'success',
        ];
    }

    public function unsuspend($userId)
    {
        $userId = (int) $userId;

        $user = get_userdata($userId);

        if (!$user) {
            return [
                'status' => 'error',
                'code' => 'user_not_exist',
            ];
        }

        delete_user_meta($userId, self::META_KEY);

        return [
            'status' => 'success',
            'code' => 'success',
        ];
    }

    protected function isLastAdministrator($user)
    {
        if (!in_array('administrator', (array) $user->roles, true)) {
            return false;
        }

        $admins = get_users([
            'role' => 'administrator',
            'fields' => 'ID',
            'number' => 2,
        ]);

        return count($admins) <= 1;
    }
}
