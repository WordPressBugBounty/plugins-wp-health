<?php
namespace WPUmbrella\CLI;

use WPUmbrella\Services\TwoFactor\TwoFactorReset;

if (!defined('ABSPATH')) {
    exit;
}

class TwoFactorResetCommand
{
    /**
     * Reset WP Umbrella two-factor authentication for a user.
     *
     * The authenticator app and every remaining recovery code stop working. If
     * the site requires two-factor authentication, the user is asked to set it
     * up again the next time they log in.
     *
     * ## OPTIONS
     *
     * <user>
     * : The user to reset, by id, login or email.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp wp-umbrella 2fa-reset 1
     *     wp wp-umbrella 2fa-reset jane@example.com --yes
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assocArgs)
    {
        $identifier = isset($args[0]) ? (string) $args[0] : '';

        if ($identifier === '') {
            \WP_CLI::error('A user id, login or email is required.');
            return;
        }

        $user = $this->resolveUser($identifier);

        if (!$user) {
            \WP_CLI::error(sprintf('No user found for "%s".', $identifier));
            return;
        }

        \WP_CLI::confirm(
            sprintf('Reset two-factor authentication for %s (ID %d)?', $user->user_login, (int) $user->ID),
            $assocArgs
        );

        $wasEnrolled = $this->resolveService()->reset($user);

        if (!$wasEnrolled) {
            \WP_CLI::success(sprintf('%s had no two-factor authentication set up. Nothing left to clear.', $user->user_login));
            return;
        }

        \WP_CLI::success(sprintf('Two-factor authentication reset for %s.', $user->user_login));
    }

    /**
     * @param string $identifier
     *
     * @return \WP_User|false
     */
    protected function resolveUser($identifier)
    {
        if (ctype_digit($identifier)) {
            return get_user_by('id', (int) $identifier);
        }

        if (is_email($identifier)) {
            return get_user_by('email', $identifier);
        }

        return get_user_by('login', $identifier);
    }

    /**
     * @return TwoFactorReset
     */
    protected function resolveService()
    {
        return new TwoFactorReset();
    }
}
