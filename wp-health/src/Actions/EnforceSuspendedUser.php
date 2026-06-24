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
    }

    public function blockSuspended($user)
    {
        if (!($user instanceof WP_User)) {
            return $user;
        }

        if (!get_user_meta($user->ID, self::META_KEY, true)) {
            return $user;
        }

        return new WP_Error(
            'wp_umbrella_user_suspended',
            __('This account has been suspended.', 'wp-umbrella')
        );
    }
}
