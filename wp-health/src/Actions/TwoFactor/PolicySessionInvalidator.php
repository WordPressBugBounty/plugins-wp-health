<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\TwoFactor\TwoFactorPolicy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Drops the sessions covered by the two factor policy when it is switched on,
 * so everyone comes back through the second factor. The session of the operator
 * doing the switch is kept.
 *
 * On multisite the scope is the current site plus the network administrators:
 * walking every site of a network would be unbounded work inside one request.
 */
class PolicySessionInvalidator implements ExecuteHooks
{
    public function hooks()
    {
        add_action('wp_umbrella_two_factor_policy_changed', [$this, 'onPolicyChanged'], 10, 1);
    }

    /**
     * @param bool $enabled
     *
     * @return void
     */
    public function onPolicyChanged($enabled = false)
    {
        if (!$enabled || !class_exists('WP_Session_Tokens')) {
            return;
        }

        $currentUserId = get_current_user_id();
        $currentToken = function_exists('wp_get_session_token') ? wp_get_session_token() : '';

        foreach ($this->coveredUserIds() as $userId) {
            $tokens = \WP_Session_Tokens::get_instance($userId);

            if ($userId === $currentUserId && $currentToken !== '') {
                $tokens->destroy_others($currentToken);
                continue;
            }

            $tokens->destroy_all();
        }
    }

    /**
     * Users the policy applies to. Enumerating by role keeps the query bounded
     * on sites whose user table is mostly customers.
     *
     * @return array
     */
    protected function coveredUserIds()
    {
        $userIds = get_users([
            'role__in' => $this->coveredRoles(),
            'fields' => 'ID',
        ]);

        $userIds = array_map('intval', is_array($userIds) ? $userIds : []);

        if (function_exists('is_multisite') && is_multisite() && function_exists('get_super_admins')) {
            foreach (get_super_admins() as $login) {
                $superAdmin = get_user_by('login', $login);

                if ($superAdmin instanceof \WP_User) {
                    $userIds[] = (int) $superAdmin->ID;
                }
            }
        }

        return array_unique($userIds);
    }

    /**
     * @return array
     */
    protected function coveredRoles()
    {
        $roles = [];

        if (!function_exists('wp_roles')) {
            return ['administrator'];
        }

        foreach (wp_roles()->roles as $slug => $role) {
            if (!empty($role['capabilities'][TwoFactorPolicy::CAPABILITY])) {
                $roles[] = $slug;
            }
        }

        return empty($roles) ? ['administrator'] : $roles;
    }
}
