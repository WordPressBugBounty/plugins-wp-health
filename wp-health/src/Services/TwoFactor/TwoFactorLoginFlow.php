<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class TwoFactorLoginFlow
{
    const SKIP = 'skip';

    const SETUP = 'setup';

    const SETUP_FAILED = 'setup_failed';

    const SETUP_UNAVAILABLE = 'setup_unavailable';

    const ENROLLED = 'enrolled';

    const CHALLENGE = 'challenge';

    const CHALLENGE_FAILED_TOTP = 'challenge_failed_totp';

    const CHALLENGE_FAILED_RECOVERY = 'challenge_failed_recovery';

    const CHALLENGE_UNAVAILABLE = 'challenge_unavailable';

    const RECOVERED = 'recovered';

    const STAGE_ENROLLED = 'enrolled';

    const LOCKED = 'locked';

    const COMPLETE = 'complete';

    /**
     * @var TwoFactorPolicy
     */
    protected $policy;

    /**
     * @var SecretStore
     */
    protected $secretStore;

    /**
     * @var TotpGenerator
     */
    protected $totp;

    /**
     * @var RecoveryCodes
     */
    protected $recoveryCodes;

    /**
     * @var ChallengeThrottle
     */
    protected $throttle;

    /**
     * @var TotpReplayGuard
     */
    protected $replayGuard;

    public function __construct(
        ?TwoFactorPolicy $policy = null,
        ?SecretStore $secretStore = null,
        ?TotpGenerator $totp = null,
        ?RecoveryCodes $recoveryCodes = null,
        ?ChallengeThrottle $throttle = null,
        ?TotpReplayGuard $replayGuard = null
    ) {
        $this->policy = $policy !== null ? $policy : new TwoFactorPolicy();
        $this->secretStore = $secretStore !== null ? $secretStore : new SecretStore();
        $this->totp = $totp !== null ? $totp : new TotpGenerator();
        $this->recoveryCodes = $recoveryCodes !== null ? $recoveryCodes : new RecoveryCodes();
        $this->throttle = $throttle !== null ? $throttle : new ChallengeThrottle();
        $this->replayGuard = $replayGuard !== null ? $replayGuard : new TotpReplayGuard();
    }

    /**
     * @param \WP_User|null $user
     *
     * @return string
     */
    public function decideOnLogin($user)
    {
        if (!$this->policy->appliesToUser($user)) {
            return self::SKIP;
        }

        if (!$this->secretStore->isEnrolled($user->ID)) {
            return self::SETUP;
        }

        return self::CHALLENGE;
    }

    /**
     * @param \WP_User|null $user
     * @param string        $step
     * @param string        $candidateSecret
     * @param string        $code
     * @param string        $stage
     *
     * @return string
     */
    public function decideSetup($user, $step, $candidateSecret, $code, $stage = '')
    {
        if (!$this->policy->appliesToUser($user)) {
            return self::SKIP;
        }

        if ($step === 'confirm') {
            if ($stage !== self::STAGE_ENROLLED || !$this->secretStore->hasSecret($user->ID)) {
                return self::SETUP;
            }

            return self::COMPLETE;
        }

        if ($stage !== self::STAGE_ENROLLED && $this->secretStore->isEnrolled($user->ID)) {
            return self::CHALLENGE;
        }

        if (!is_string($candidateSecret) || $candidateSecret === '') {
            return self::SETUP_FAILED;
        }

        $counter = $this->totp->matchCounter($candidateSecret, $code);

        if ($counter === null) {
            return self::SETUP_FAILED;
        }

        if (!$this->secretStore->setSecret($user->ID, $candidateSecret)) {
            return self::SETUP_UNAVAILABLE;
        }

        $this->replayGuard->clear($user->ID);
        $this->replayGuard->accept($user->ID, $counter);

        return self::ENROLLED;
    }

    /**
     * @param \WP_User|null $user
     * @param string        $code
     * @param string        $recoveryCode
     *
     * @return string
     */
    public function decideChallenge($user, $code, $recoveryCode)
    {
        if (!$this->policy->appliesToUser($user)) {
            return self::SKIP;
        }

        if ($this->throttle->registerFailure($user->ID)) {
            return self::LOCKED;
        }

        if (is_string($recoveryCode) && $recoveryCode !== '') {
            if ($this->recoveryCodes->consume($user->ID, $recoveryCode)) {
                $this->throttle->clear($user->ID);

                return self::RECOVERED;
            }

            return self::CHALLENGE_FAILED_RECOVERY;
        }

        $secret = $this->secretStore->getSecret($user->ID);

        if ($secret === null) {
            if (!$this->secretStore->isEnrolled($user->ID)) {
                $this->throttle->clear($user->ID);

                return self::SETUP;
            }

            return self::CHALLENGE_UNAVAILABLE;
        }

        $counter = $this->totp->matchCounter($secret, $code);

        if ($counter === null) {
            return self::CHALLENGE_FAILED_TOTP;
        }

        if (!$this->replayGuard->accept($user->ID, $counter)) {
            return self::CHALLENGE_FAILED_TOTP;
        }

        $this->throttle->clear($user->ID);

        return self::COMPLETE;
    }

    /**
     * @param int $userId
     *
     * @return array
     */
    public function enroll($userId)
    {
        return $this->recoveryCodes->regenerate($userId);
    }

    /**
     * @param int $userId
     *
     * @return int
     */
    public function remainingRecoveryCodes($userId)
    {
        return $this->recoveryCodes->remaining($userId);
    }

    /**
     * @param string $secret
     * @param string $accountName
     * @param string $issuer
     *
     * @return string
     */
    public function provisioningUri($secret, $accountName, $issuer)
    {
        return $this->totp->provisioningUri($secret, $accountName, $issuer);
    }

    /**
     * @return string
     */
    public function generateSecret()
    {
        return $this->totp->generateSecret();
    }
}
