<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HiddenAdminAnalyzer
{
    const APPLICATION_PASSWORDS_META_KEY = '_application_passwords';

    const WORDPRESS_HASH_PREFIXES = ['$P$', '$wp$', '$2y$'];

    const PROVENANCE_REASON_PASSWORD_HASH = 'password_hash';
    const PROVENANCE_REASON_MISSING_USER_META = 'missing_user_meta';
    const PROVENANCE_REASON_CAPABILITY_SERIALIZATION = 'capability_serialization';

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

        $rawIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = %s AND meta_value LIKE %s",
                $capabilitiesKey,
                '%' . $wpdb->esc_like('"administrator"') . '%'
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

            if (isset($knownAdminIds[$id])) {
                continue;
            }

            $user = get_userdata($id);

            if (!$user || !in_array('administrator', (array) $user->roles, true)) {
                continue;
            }

            $hiddenIds[] = $id;
        }

        $auditedUserIds = array_values(
            array_unique(array_merge(array_keys($knownAdminIds), $hiddenIds))
        );

        return [
            'administrators' => $administrators,
            'orphan_capability_user_ids' => array_values(array_unique($orphanIds)),
            'hidden_admin_user_ids' => array_values(array_unique($hiddenIds)),
            'application_passwords' => $this->collectApplicationPasswords($auditedUserIds),
            'application_passwords_available' => $this->areApplicationPasswordsAvailable(),
            'provenance_suspects' => $this->collectProvenanceSuspects($auditedUserIds),
            'has_findings' => !empty($orphanIds) || !empty($hiddenIds),
        ];
    }

    /**
     * @param int[] $userIds
     * @return array[]
     */
    protected function collectProvenanceSuspects($userIds)
    {
        global $wpdb;

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '%d'));

        $users = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, user_login, user_pass FROM {$wpdb->users} WHERE ID IN ($placeholders)",
                $userIds
            )
        );

        if (empty($users)) {
            return [];
        }

        $userLevelKey = $wpdb->prefix . 'user_level';
        $capabilitiesKey = $wpdb->prefix . 'capabilities';

        $metaRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta}
                 WHERE meta_key IN (%s, %s, %s) AND user_id IN ($placeholders)",
                array_merge(['nickname', $userLevelKey, $capabilitiesKey], $userIds)
            )
        );

        $metaByUser = [];
        foreach ($metaRows as $row) {
            $metaByUser[(int) $row->user_id][$row->meta_key] = $row->meta_value;
        }

        $suspects = [];

        foreach ($users as $user) {
            $id = (int) $user->ID;
            $meta = isset($metaByUser[$id]) ? $metaByUser[$id] : [];
            $reasons = [];

            if (!$this->isWordPressPasswordHash($user->user_pass)) {
                $reasons[] = self::PROVENANCE_REASON_PASSWORD_HASH;
            }

            if (!isset($meta['nickname']) || !isset($meta[$userLevelKey])) {
                $reasons[] = self::PROVENANCE_REASON_MISSING_USER_META;
            }

            if (isset($meta[$capabilitiesKey])
                && $this->hasStringSerializedAdministratorRole($meta[$capabilitiesKey])
            ) {
                $reasons[] = self::PROVENANCE_REASON_CAPABILITY_SERIALIZATION;
            }

            if (!empty($reasons)) {
                $suspects[] = [
                    'user_id' => $id,
                    'user_login' => $user->user_login,
                    'reasons' => $reasons,
                ];
            }
        }

        return $suspects;
    }

    /**
     * @param mixed $hash
     * @return bool
     */
    protected function isWordPressPasswordHash($hash)
    {
        if (!is_string($hash) || $hash === '') {
            return false;
        }

        foreach (self::WORDPRESS_HASH_PREFIXES as $prefix) {
            if (strpos($hash, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $serialized
     * @return bool
     */
    protected function hasStringSerializedAdministratorRole($serialized)
    {
        if (!is_string($serialized)) {
            return false;
        }

        if (!preg_match('/s:13:"administrator";([a-z]):/', $serialized, $matches)) {
            return false;
        }

        return $matches[1] !== 'b';
    }

    /**
     * @param int[] $userIds
     * @return array[]
     */
    protected function collectApplicationPasswords($userIds)
    {
        global $wpdb;

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta}
                 WHERE meta_key = %s AND user_id IN ($placeholders)",
                array_merge([self::APPLICATION_PASSWORDS_META_KEY], $userIds)
            )
        );

        if (empty($rows)) {
            return [];
        }

        $passwords = [];

        foreach ($rows as $row) {
            $items = is_string($row->meta_value)
                ? @unserialize($row->meta_value, ['allowed_classes' => false])
                : null;

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $passwords[] = [
                    'user_id' => (int) $row->user_id,
                    'name' => $this->readString($item, 'name', ''),
                    'uuid' => $this->readString($item, 'uuid', ''),
                    'app_id' => $this->readString($item, 'app_id', ''),
                    'created' => $this->readTimestamp($item, 'created'),
                    'last_used' => $this->readTimestamp($item, 'last_used'),
                    'last_ip' => $this->readString($item, 'last_ip', null),
                ];
            }
        }

        return $passwords;
    }

    /**
     * @param array $item
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    protected function readString($item, $key, $default)
    {
        if (!isset($item[$key]) || !is_scalar($item[$key]) || $item[$key] === '') {
            return $default;
        }

        return (string) $item[$key];
    }

    /**
     * @param array $item
     * @param string $key
     * @return int|null
     */
    protected function readTimestamp($item, $key)
    {
        if (!isset($item[$key]) || !is_numeric($item[$key])) {
            return null;
        }

        return (int) $item[$key];
    }

    /**
     * @return bool
     */
    protected function areApplicationPasswordsAvailable()
    {
        if (!function_exists('wp_is_application_passwords_available')) {
            return false;
        }

        return (bool) wp_is_application_passwords_available();
    }
}
