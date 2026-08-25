<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\TwoFactor\TwoFactorPolicy;

if (!defined('ABSPATH')) {
    exit;
}

class AuthChannelGuard implements ExecuteHooks
{
    /**
     * @var TwoFactorPolicy
     */
    protected $policy;

    public function __construct(?TwoFactorPolicy $policy = null)
    {
        $this->policy = $policy !== null ? $policy : new TwoFactorPolicy();
    }

    public function hooks()
    {
        add_filter('authenticate', [$this, 'blockXmlRpc'], 30, 1);
        add_action('wp_authenticate_application_password_errors', [$this, 'blockApplicationPassword'], 10, 2);
        add_filter('wp_is_application_passwords_available_for_user', [$this, 'hideApplicationPasswords'], 10, 2);
    }

    /**
     * @param \WP_User|\WP_Error|null $user
     *
     * @return \WP_User|\WP_Error|null
     */
    public function blockXmlRpc($user)
    {
        if (!defined('XMLRPC_REQUEST') || !XMLRPC_REQUEST) {
            return $user;
        }

        if (!$this->isCovered($user)) {
            return $user;
        }

        return new \WP_Error(
            'wp_umbrella_two_factor_required',
            __('This account requires two-factor authentication, which XML-RPC cannot ask for. Log in from the site instead.', 'wp-health')
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
        if (!is_object($error) || !method_exists($error, 'add') || !$this->isCovered($user)) {
            return;
        }

        $error->add(
            'wp_umbrella_two_factor_required',
            __('This account requires two-factor authentication, so application passwords are turned off for it.', 'wp-health')
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
        return $this->isCovered($user) ? false : $available;
    }

    /**
     * @param mixed $user
     *
     * @return bool
     */
    protected function isCovered($user)
    {
        if (!is_object($user) || !($user instanceof \WP_User)) {
            return false;
        }

        return $this->policy->appliesToUser($user);
    }
}
