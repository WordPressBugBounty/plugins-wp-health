<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;
use WPUmbrella\Actions\ActivityLog\Framework\WPUmbrellaContext;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures user and authentication events.
 *
 * Event keys emitted:
 * - user.login.success     (HIGH)
 * - user.login.failed      (HIGH)
 * - user.logout            (LOW)
 * - user.created           (CRITICAL when role=administrator, else HIGH)
 * - user.updated           (MEDIUM)
 * - user.deleted           (CRITICAL when role=administrator, else HIGH)
 * - user.role.changed      (CRITICAL when promoting to administrator, else HIGH)
 * - user.password.reset    (HIGH)
 *
 * Convention: AbstractSensor auto captures the *current* WordPress user
 * (the one performing the action) into wpUserId / wpUsername. When the
 * event concerns a different user (admin acting on someone else), that
 * target user is recorded under targetUserId / targetUsername.
 *
 * The administrator role gets a dedicated CRITICAL severity so downstream
 * alerting can wire on "someone is messing with admin accounts" without
 * having to parse role lists from event metadata.
 */
class UserSensor extends AbstractSensor
{
    const ROLE_ADMINISTRATOR = 'administrator';

    /**
     * Snapshot of user state captured before profile_update so we can
     * diff against the new state and surface what actually changed.
     *
     * @var array<int, array{login: string, email: string, displayName: string, roles: array}>
     */
    protected $beforeUpdateCache = [];

    /**
     * User ids whose user_register fired during this PHP request. Lets
     * us suppress the profile_update events that WP fires as part of the
     * create flow (admin form fills display_name / nickname after insert).
     *
     * @var array<int, bool>
     */
    protected $createdInThisRequest = [];

    /**
     * True while we are inside a wp_insert_user call for a NEW user.
     * Set by the wp_pre_insert_user_data filter, reset by user_register.
     *
     * Needed because wp_insert_user fires set_user_role BEFORE user_register
     * for the freshly created user. Without this flag, our onUserRoleChanged
     * runs and emits a redundant user.role.changed event for what is in fact
     * the initial role assignment of a brand new user.
     *
     * @var bool
     */
    protected $isInsertingNewUser = false;

    /**
     * @return void
     */
    public function register()
    {
        add_filter('wp_pre_insert_user_data', [$this, 'onPreInsertUserData'], 10, 2);
        add_action('wp_login', [$this, 'onLoginSuccess'], 10, 2);
        add_action('wp_login_failed', [$this, 'onLoginFailed'], 10, 2);
        add_action('wp_logout', [$this, 'onLogout'], 10, 1);
        add_action('user_register', [$this, 'onUserCreated'], 10, 2);
        add_action('profile_update', [$this, 'onBeforeProfileUpdate'], 1, 2);
        add_action('profile_update', [$this, 'onProfileUpdated'], 99, 2);
        add_action('delete_user', [$this, 'onBeforeDeleteUser'], 10, 1);
        add_action('deleted_user', [$this, 'onUserDeleted'], 10, 3);
        add_action('set_user_role', [$this, 'onUserRoleChanged'], 10, 3);
        add_action('after_password_reset', [$this, 'onPasswordReset'], 10, 1);
    }

    /**
     * Filter callback. Raises the inserting-new-user flag at the entry of
     * wp_insert_user so the set_user_role hook that follows can detect we
     * are mid-creation and skip the redundant role-changed event.
     *
     * @param array $data
     * @param bool  $update
     *
     * @return array
     */
    public function onPreInsertUserData($data, $update = false)
    {
        if ($update !== true) {
            $this->isInsertingNewUser = true;
        }

        return $data;
    }

    public function onLoginSuccess($userLogin, $user = null)
    {
        $context = [
            'targetUsername' => is_string($userLogin) ? $userLogin : null,
        ];

        if (is_object($user)) {
            $context['targetUserId'] = isset($user->ID) ? (int) $user->ID : null;
            $context['targetUserRoles'] = isset($user->roles) && is_array($user->roles) ? array_values($user->roles) : [];
        }

        $this->recordEvent('user.login.success', 'HIGH', $context);
    }

    public function onLoginFailed($username, $error = null)
    {
        $this->recordEvent('user.login.failed', 'HIGH', [
            'targetUsername' => is_string($username) ? $username : null,
            'errorCode' => is_object($error) && method_exists($error, 'get_error_code') ? (string) $error->get_error_code() : null,
        ]);
    }

    public function onLogout($userId = 0)
    {
        $this->recordEvent('user.logout', 'LOW', [
            'targetUserId' => (int) $userId > 0 ? (int) $userId : null,
        ]);
    }

    public function onUserCreated($userId, $userdata = [])
    {
        $userId = (int) $userId;
        $info = $this->getUserInfo($userId);
        $roles = $info !== null ? $info['roles'] : [];
        $severity = self::isAdministrator($roles) ? 'CRITICAL' : 'HIGH';

        $this->isInsertingNewUser = false;
        $this->createdInThisRequest[$userId] = true;

        $this->recordEvent('user.created', $severity, [
            'targetUserId' => $userId,
            'targetUsername' => $info !== null ? $info['login'] : null,
            'targetEmail' => $info !== null ? $info['email'] : null,
            'targetUserRoles' => $roles,
        ]);
    }

    public function onBeforeProfileUpdate($userId, $oldUserData = null)
    {
        $info = $this->getUserInfo($userId);

        if ($info !== null) {
            $this->beforeUpdateCache[(int) $userId] = $info;
        }
    }

    public function onProfileUpdated($userId, $oldUserData = null)
    {
        $userId = (int) $userId;
        $before = isset($this->beforeUpdateCache[$userId]) ? $this->beforeUpdateCache[$userId] : null;
        unset($this->beforeUpdateCache[$userId]);

        if (isset($this->createdInThisRequest[$userId])) {
            return;
        }

        $after = $this->getUserInfo($userId);

        $changedFields = [];

        if ($before !== null && $after !== null) {
            foreach (['login', 'email', 'displayName', 'roles'] as $field) {
                if ($before[$field] !== $after[$field]) {
                    $changedFields[] = $field;
                }
            }
        }

        $this->recordEvent('user.updated', 'MEDIUM', [
            'targetUserId' => $userId,
            'targetUsername' => $after !== null ? $after['login'] : null,
            'changedFields' => $changedFields,
        ]);
    }

    public function onBeforeDeleteUser($userId)
    {
        $info = $this->getUserInfo($userId);

        if ($info !== null) {
            $this->beforeUpdateCache[(int) $userId] = $info;
        }

        WPUmbrellaContext::setInUserDeletion(true);
    }

    public function onUserDeleted($userId, $reassign = null, $user = null)
    {
        WPUmbrellaContext::setInUserDeletion(false);

        $userId = (int) $userId;
        $info = isset($this->beforeUpdateCache[$userId]) ? $this->beforeUpdateCache[$userId] : null;
        unset($this->beforeUpdateCache[$userId]);

        if ($info === null && is_object($user)) {
            $info = [
                'login' => isset($user->user_login) ? (string) $user->user_login : '',
                'email' => isset($user->user_email) ? (string) $user->user_email : '',
                'displayName' => isset($user->display_name) ? (string) $user->display_name : '',
                'roles' => isset($user->roles) && is_array($user->roles) ? array_values($user->roles) : [],
            ];
        }

        $deletedRoles = $info !== null ? $info['roles'] : [];
        $severity = self::isAdministrator($deletedRoles) ? 'CRITICAL' : 'HIGH';

        $this->recordEvent('user.deleted', $severity, [
            'targetUserId' => $userId,
            'targetUsername' => $info !== null ? $info['login'] : null,
            'targetEmail' => $info !== null ? $info['email'] : null,
            'targetUserRoles' => $deletedRoles,
            'reassignedTo' => $reassign !== null ? (int) $reassign : null,
        ]);
    }

    public function onUserRoleChanged($userId, $newRole, $oldRoles = [])
    {
        $userId = (int) $userId;

        if ($this->isInsertingNewUser || isset($this->createdInThisRequest[$userId])) {
            return;
        }

        $info = $this->getUserInfo($userId);
        $previousRoles = is_array($oldRoles) ? array_values($oldRoles) : [];

        $promotedToAdmin = is_string($newRole)
            && $newRole === self::ROLE_ADMINISTRATOR
            && !self::isAdministrator($previousRoles);
        $demotedFromAdmin = self::isAdministrator($previousRoles)
            && (!is_string($newRole) || $newRole !== self::ROLE_ADMINISTRATOR);

        $severity = ($promotedToAdmin || $demotedFromAdmin) ? 'CRITICAL' : 'HIGH';

        $this->recordEvent('user.role.changed', $severity, [
            'targetUserId' => (int) $userId,
            'targetUsername' => $info !== null ? $info['login'] : null,
            'newRole' => is_string($newRole) ? $newRole : null,
            'previousRoles' => $previousRoles,
        ]);
    }

    /**
     * Returns true when the given roles array contains the administrator role.
     *
     * @param array $roles
     *
     * @return bool
     */
    protected static function isAdministrator($roles)
    {
        if (!is_array($roles)) {
            return false;
        }

        return in_array(self::ROLE_ADMINISTRATOR, $roles, true);
    }

    public function onPasswordReset($user)
    {
        $userId = is_object($user) && isset($user->ID) ? (int) $user->ID : 0;
        $username = is_object($user) && isset($user->user_login) ? (string) $user->user_login : null;

        $this->recordEvent('user.password.reset', 'HIGH', [
            'targetUserId' => $userId > 0 ? $userId : null,
            'targetUsername' => $username,
        ]);
    }

    /**
     * @param int $userId
     *
     * @return array{login: string, email: string, displayName: string, roles: array}|null
     */
    protected function getUserInfo($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0 || !function_exists('get_userdata')) {
            return null;
        }

        $user = get_userdata($userId);

        if (!is_object($user)) {
            return null;
        }

        return [
            'login' => isset($user->user_login) ? (string) $user->user_login : '',
            'email' => isset($user->user_email) ? (string) $user->user_email : '',
            'displayName' => isset($user->display_name) ? (string) $user->display_name : '',
            'roles' => isset($user->roles) && is_array($user->roles) ? array_values($user->roles) : [],
        ];
    }
}
