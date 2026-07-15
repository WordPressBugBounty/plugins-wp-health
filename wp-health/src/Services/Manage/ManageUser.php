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

    public function cleanOrphanCapabilities($userId)
    {
        global $wpdb;

        $userId = (int) $userId;

        if ($userId <= 0) {
            return [
                'status' => 'error',
                'code' => 'invalid_user_id',
            ];
        }

        $existingUser = $wpdb->get_var(
            $wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID = %d", $userId)
        );

        if (!empty($existingUser)) {
            return [
                'status' => 'error',
                'code' => 'user_exists',
            ];
        }

        $metaKeys = [
            $wpdb->prefix . 'capabilities',
            $wpdb->prefix . 'user_level',
        ];

        $placeholders = implode(', ', array_fill(0, count($metaKeys), '%s'));

        $found = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key IN ({$placeholders})",
                array_merge([$userId], $metaKeys)
            )
        );

        if ($found === 0) {
            return [
                'status' => 'error',
                'code' => 'no_orphan',
            ];
        }

        $deleted = 0;

        foreach ($metaKeys as $metaKey) {
            $deleted += (int) $wpdb->delete($wpdb->usermeta, [
                'user_id' => $userId,
                'meta_key' => $metaKey,
            ]);
        }

        return [
            'status' => 'success',
            'code' => 'success',
            'deleted' => $deleted,
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
