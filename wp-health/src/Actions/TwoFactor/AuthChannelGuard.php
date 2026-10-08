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
