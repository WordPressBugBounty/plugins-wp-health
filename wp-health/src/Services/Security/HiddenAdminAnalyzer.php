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
    const PROVENANCE_REASON_CREATED_WITH_UNLISTED_CODE = 'created_with_unlisted_code';
    const PROVENANCE_REASON_LOGIN_MATCHES_SITE_HOST = 'login_matches_site_host';

    const PROVENANCE_STRONG_REASONS = [
        self::PROVENANCE_REASON_PASSWORD_HASH,
        self::PROVENANCE_REASON_MISSING_USER_META,
        self::PROVENANCE_REASON_CAPABILITY_SERIALIZATION,
        self::PROVENANCE_REASON_CREATED_WITH_UNLISTED_CODE,
    ];

    const UNLISTED_CODE_WINDOW = 600;

    const MIN_SITE_HOST_LABEL_LENGTH = 5;

    const CORE_ROLES = [
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
    ];

    const CRITICAL_CAPABILITIES = [
        'install_plugins',
        'install_themes',
        'edit_plugins',
        'edit_themes',
        'edit_files',
        'update_core',
        'manage_options',
        'create_users',
        'edit_users',
        'delete_users',
        'promote_users',
        'unfiltered_upload',
    ];

    /**
     * @var UnlistedCodeAnalyzer|null
     */
    protected $unlistedCodeAnalyzer;

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
                 WHERE meta_key = %s
                 AND (
                     meta_value LIKE %s
                     OR CAST(meta_value AS BINARY) LIKE %s
                     OR CAST(meta_value AS BINARY) LIKE %s
                 )",
                $capabilitiesKey,
                '%' . $wpdb->esc_like('"administrator"') . '%',
                '%' . $wpdb->esc_like('{S:') . '%',
                '%' . $wpdb->esc_like(';S:') . '%'
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
            'privileged_roles' => $this->collectPrivilegedRoles(),
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
                "SELECT ID, user_login, user_pass, user_registered FROM {$wpdb->users} WHERE ID IN ($placeholders)",
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

        $candidates = [];
        $unlistedCodeTimes = $this->getUnlistedCodeTimes();
        $siteHostLabel = $this->getSiteHostLabel();

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

            if ($this->matchesUnlistedCodeWindow($user->user_registered, $unlistedCodeTimes)) {
                $reasons[] = self::PROVENANCE_REASON_CREATED_WITH_UNLISTED_CODE;
            }

            if ($this->loginMatchesSiteHost($user->user_login, $siteHostLabel)) {
                $reasons[] = self::PROVENANCE_REASON_LOGIN_MATCHES_SITE_HOST;
            }

            $candidates[] = [
                'user_id' => $id,
                'user_login' => $user->user_login,
                'reasons' => $reasons,
            ];
        }

        return $this->reportableSuspects($candidates);
    }

    /**
     * @param array[] $candidates
     * @return array[]
     */
    protected function reportableSuspects($candidates)
    {
        $suspects = [];

        foreach ($candidates as $candidate) {
            if (empty($candidate['reasons'])) {
                continue;
            }

            $confidence = $this->confidence($candidate['reasons']);

            if ($confidence !== 'high') {
                continue;
            }

            $candidate['confidence'] = $confidence;
            $suspects[] = $candidate;
        }

        return $suspects;
    }

    /**
     * @param string[] $reasons
     * @return string
     */
    protected function confidence($reasons)
    {
        foreach ($reasons as $reason) {
            if (in_array($reason, self::PROVENANCE_STRONG_REASONS, true)) {
                return 'high';
            }
        }

        return 'low';
    }

    /**
     * @return array[]
     */
    protected function collectPrivilegedRoles()
    {
        return $this->findPrivilegedRoles($this->readRoleDefinitions());
    }

    /**
     * @return array
     */
    protected function readRoleDefinitions()
    {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $roles = wp_roles();

        if (!is_object($roles) || !isset($roles->roles) || !is_array($roles->roles)) {
            return [];
        }

        return $roles->roles;
    }

    /**
     * @param array $roles
     * @return array[]
     */
    protected function findPrivilegedRoles($roles)
    {
        $privileged = [];

        foreach ($roles as $slug => $definition) {
            if (!is_string($slug) || $slug === '' || in_array($slug, self::CORE_ROLES, true)) {
                continue;
            }

            $capabilities = isset($definition['capabilities']) && is_array($definition['capabilities'])
                ? $definition['capabilities']
                : [];

            $critical = $this->criticalCapabilitiesOf($capabilities);

            if (empty($critical)) {
                continue;
            }

            $privileged[] = [
                'role' => $slug,
                'name' => isset($definition['name']) && is_scalar($definition['name'])
                    ? (string) $definition['name']
                    : $slug,
                'critical_capabilities' => $critical,
            ];
        }

        return $privileged;
    }

    /**
     * @param array $capabilities
     * @return string[]
     */
    protected function criticalCapabilitiesOf($capabilities)
    {
        $granted = [];

        foreach (self::CRITICAL_CAPABILITIES as $capability) {
            if (!empty($capabilities[$capability])) {
                $granted[] = $capability;
            }
        }

        return $granted;
    }

    /**
     * @param UnlistedCodeAnalyzer $analyzer
     */
    public function setUnlistedCodeAnalyzer($analyzer)
    {
        $this->unlistedCodeAnalyzer = $analyzer;
    }

    /**
     * @return int[]
     */
    protected function getUnlistedCodeTimes()
    {
        if ($this->unlistedCodeAnalyzer === null && !function_exists('wp_umbrella_get_service')) {
            return [];
        }

        try {
            if ($this->unlistedCodeAnalyzer === null) {
                $this->unlistedCodeAnalyzer = wp_umbrella_get_service('UnlistedCodeAnalyzer');
            }

            return $this->unlistedCodeAnalyzer->getModificationTimes();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param mixed $registered
     * @param int[] $times
     * @return bool
     */
    protected function matchesUnlistedCodeWindow($registered, $times)
    {
        if (empty($times) || !is_string($registered) || $registered === '') {
            return false;
        }

        $timestamp = strtotime($registered . ' UTC');

        if ($timestamp === false) {
            return false;
        }

        foreach ($times as $time) {
            if (abs($time - $timestamp) <= self::UNLISTED_CODE_WINDOW) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string|null
     */
    protected function getSiteHostLabel()
    {
        $host = parse_url(home_url(), PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = preg_replace('/^www\./i', '', strtolower($host));
        $parts = explode('.', $host);
        $label = preg_replace('/[^a-z0-9]/', '', $parts[0]);

        if (!is_string($label) || strlen($label) < self::MIN_SITE_HOST_LABEL_LENGTH) {
            return null;
        }

        return $label;
    }

    /**
     * @param mixed       $login
     * @param string|null $label
     * @return bool
     */
    protected function loginMatchesSiteHost($login, $label)
    {
        if ($label === null || !is_string($login) || $login === '') {
            return false;
        }

        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($login));

        if (!is_string($normalized) || $normalized === '' || $normalized === $label) {
            return false;
        }

        return strpos($normalized, $label) !== false;
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
        if (!is_string($serialized) || $serialized === '') {
            return false;
        }

        $capabilities = @unserialize($serialized, ['allowed_classes' => false]);

        if (!is_array($capabilities)) {
            return false;
        }

        foreach ($capabilities as $capability => $granted) {
            if ((string) $capability !== 'administrator') {
                continue;
            }

            return !is_bool($granted);
        }

        return false;
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
