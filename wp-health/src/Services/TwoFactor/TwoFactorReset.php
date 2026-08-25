<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class TwoFactorReset
{
    /**
     * @var SecretStore
     */
    protected $secretStore;

    /**
     * @var RecoveryCodes
     */
    protected $recoveryCodes;

    /**
     * @var TotpReplayGuard
     */
    protected $replayGuard;

    public function __construct(
        ?SecretStore $secretStore = null,
        ?RecoveryCodes $recoveryCodes = null,
        ?TotpReplayGuard $replayGuard = null
    ) {
        $this->secretStore = $secretStore !== null ? $secretStore : new SecretStore();
        $this->recoveryCodes = $recoveryCodes !== null ? $recoveryCodes : new RecoveryCodes();
        $this->replayGuard = $replayGuard !== null ? $replayGuard : new TotpReplayGuard();
    }

    /**
     * @param \WP_User|null $user
     * @param \WP_User|null $actor
     *
     * @return bool
     */
    public function reset($user, $actor = null)
    {
        if (!is_object($user) || empty($user->ID)) {
            return false;
        }

        $userId = (int) $user->ID;
        $wasEnrolled = $this->secretStore->isEnrolled($userId);

        $this->secretStore->clear($userId);
        $this->recoveryCodes->clear($userId);
        $this->replayGuard->clear($userId);

        do_action('wp_umbrella_two_factor_reset', $user, $actor);

        return $wasEnrolled;
    }
}
