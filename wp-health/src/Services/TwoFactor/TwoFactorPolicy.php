<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class TwoFactorPolicy
{
    const SETTING_KEY = 'require_2fa_admin';

    const CAPABILITY = 'manage_options';

    /**
     * @var CompatibilityGuard
     */
    protected $guard;

    /**
     * @var SecretStore
     */
    protected $secretStore;

    public function __construct(?CompatibilityGuard $guard = null, ?SecretStore $secretStore = null)
    {
        $this->guard = $guard !== null ? $guard : new CompatibilityGuard();
        $this->secretStore = $secretStore !== null ? $secretStore : new SecretStore();
    }

    /**
     * @return bool
     */
    public function isPolicyOn()
    {
        return (bool) wp_umbrella_get_service('HardeningSettings')->isEnabled(self::SETTING_KEY);
    }

    /**
     * @return bool
     */
    public function isEnforceable()
    {
        return $this->guard->isEnforceable();
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->isPolicyOn() && $this->isEnforceable();
    }

    /**
     * @param \WP_User|null $user
     *
     * @return bool
     */
    public function appliesToUser($user)
    {
        if (!is_object($user) || empty($user->ID)) {
            return false;
        }

        if (!$this->isPolicyOn()) {
            return false;
        }

        if (!$this->isEnforceable() && !$this->secretStore->isEnrolled($user->ID)) {
            return false;
        }

        return user_can($user, self::CAPABILITY);
    }

    /**
     * @param \WP_User|null $user
     *
     * @return bool
     */
    public function canSelfDisable($user)
    {
        if (!is_object($user) || empty($user->ID)) {
            return false;
        }

        if (!$this->isPolicyOn() || !$this->isEnforceable()) {
            return true;
        }

        return !user_can($user, self::CAPABILITY);
    }

    /**
     * @return CompatibilityGuard
     */
    public function getGuard()
    {
        return $this->guard;
    }
}
