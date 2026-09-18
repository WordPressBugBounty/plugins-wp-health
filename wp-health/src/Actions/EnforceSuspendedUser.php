<?php
namespace WPUmbrella\Actions;

use WP_Error;
use WP_User;
use WPUmbrella\Core\Hooks\ExecuteHooks;

class EnforceSuspendedUser implements ExecuteHooks
{
    const META_KEY = 'wp_umbrella_suspended';

    public function hooks()
    {
        add_filter('authenticate', [$this, 'blockSuspended'], 30, 1);
        add_action('wp_authenticate_application_password_errors', [$this, 'blockApplicationPassword'], 10, 2);
        add_filter('wp_is_application_passwords_available_for_user', [$this, 'hideApplicationPasswords'], 10, 2);
    }

    public function blockSuspended($user)
    {
        if (!$this->isSuspended($user)) {
            return $user;
        }

        return new WP_Error(
            'wp_umbrella_user_suspended',
            __('This account has been suspended.', 'wp-umbrella')
        );
    }

    /**
     * @param \WP_Error $error
     * @param \WP_User  $user
     *
     * @return void
     */
    public function blockApplicationPassword($error, $user)
    {
        if (!is_object($error) || !method_exists($error, 'add') || !$this->isSuspended($user)) {
            return;
        }

        $error->add(
            'wp_umbrella_user_suspended',
            __('This account has been suspended.', 'wp-umbrella')
        );
    }

    /**
     * @param bool     $available
     * @param \WP_User $user
     *
     * @return bool
     */
    public function hideApplicationPasswords($available, $user)
    {
        return $this->isSuspended($user) ? false : $available;
    }

    /**
     * @param mixed $user
     *
     * @return bool
     */
    protected function isSuspended($user)
    {
        if (!($user instanceof WP_User)) {
            return false;
        }

        return (bool) get_user_meta($user->ID, self::META_KEY, true);
    }
}
