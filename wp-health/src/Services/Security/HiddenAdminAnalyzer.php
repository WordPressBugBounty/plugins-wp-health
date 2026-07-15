<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HiddenAdminAnalyzer
{
    public function analyze()
    {
        global $wpdb;

        $users = get_users([
            'role' => 'administrator',
            'fields' => ['ID', 'user_login', 'user_email'],
        ]);

        $administrators = [];
        $knownAdminIds = [];

        foreach ($users as $user) {
            $id = (int) $user->ID;
            $knownAdminIds[$id] = true;
            $administrators[] = [
                'user_id' => $id,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
            ];
        }

        $capabilitiesKey = $wpdb->prefix . 'capabilities';
        $userLevelKey = $wpdb->prefix . 'user_level';

        $rawIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
                 WHERE (meta_key = %s AND meta_value LIKE %s)
                    OR (meta_key = %s AND meta_value = %s)",
                $capabilitiesKey,
                '%administrator%',
                $userLevelKey,
                '10'
            )
        );

        $existingUserIds = [];
        if (!empty($rawIds)) {
            $placeholders = implode(',', array_fill(0, count($rawIds), '%d'));
            $existing = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE ID IN ($placeholders)",
                    $rawIds
                )
            );
            foreach ($existing as $id) {
                $existingUserIds[(int) $id] = true;
            }
        }

        $orphanIds = [];
        $hiddenIds = [];

        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;

            if (!isset($existingUserIds[$id])) {
                $orphanIds[] = $id;
                continue;
            }

            if (!isset($knownAdminIds[$id])) {
                $hiddenIds[] = $id;
            }
        }

        return [
            'administrators' => $administrators,
            'orphan_capability_user_ids' => array_values(array_unique($orphanIds)),
            'hidden_admin_user_ids' => array_values(array_unique($hiddenIds)),
            'has_findings' => !empty($orphanIds) || !empty($hiddenIds),
        ];
    }
}
