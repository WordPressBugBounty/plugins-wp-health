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

        $refusal = $this->networkRefusal($userId);

        if ($refusal !== null) {
            return $refusal;
        }

        if ($this->isLastAdministrator($user)) {
            return [
                'status' => 'error',
                'code' => 'cannot_suspend_last_administrator',
            ];
        }

        update_user_meta($userId, self::META_KEY, 1);

        if (class_exists('WP_Session_Tokens')) {
            \WP_Session_Tokens::get_instance($userId)->destroy_all();
        }

        if (class_exists('WP_Application_Passwords')) {
            \WP_Application_Passwords::delete_all_application_passwords($userId);
        }

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

        $refusal = $this->networkRefusal($userId);

        if ($refusal !== null) {
            return $refusal;
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

    protected function networkRefusal($userId)
    {
        if (!is_multisite()) {
            return null;
        }

        if (!is_user_member_of_blog($userId, get_current_blog_id())) {
            return [
                'status' => 'error',
                'code' => 'user_not_member',
            ];
        }

        if (is_super_admin($userId) && !is_main_site()) {
            return [
                'status' => 'error',
                'code' => 'not_authorized',
            ];
        }

        return null;
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
            'meta_query' => [
                [
                    'key' => self::META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        return count($admins) <= 1;
    }
}
