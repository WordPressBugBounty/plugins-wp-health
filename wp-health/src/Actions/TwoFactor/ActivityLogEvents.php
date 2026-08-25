<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class ActivityLogEvents implements ExecuteHooks
{
    const EVENT_ENROLLED = 'umbrella.2fa.enrolled';

    const EVENT_CHALLENGE_FAILED = 'umbrella.2fa.challenge_failed';

    const EVENT_RECOVERY_CODE_USED = 'umbrella.2fa.recovery_code_used';

    const EVENT_DISABLED = 'umbrella.2fa.disabled';

    const EVENT_RESET = 'umbrella.2fa.reset';

    const EVENT_BYPASSED = 'umbrella.2fa.bypassed_by_umbrella';

    const EVENT_POLICY_CHANGED = 'umbrella.2fa.policy_changed';

    const EVENT_UNREADABLE = 'umbrella.2fa.unreadable';

    const FAILED_BUCKET_KEY = 'wp_umbrella_2fa_failed_bucket';

    const FAILED_WINDOW = 900;

    /**
     * @var ProtectionEventRecorder
     */
    protected $recorder;

    public function __construct(?ProtectionEventRecorder $recorder = null)
    {
        $this->recorder = $recorder !== null ? $recorder : new ProtectionEventRecorder();
    }

    public function hooks()
    {
        add_action('wp_umbrella_two_factor_enrolled', [$this, 'onEnrolled'], 10, 1);
        add_action('wp_umbrella_two_factor_challenge_failed', [$this, 'onChallengeFailed'], 10, 2);
        add_action('wp_umbrella_two_factor_recovery_code_used', [$this, 'onRecoveryCodeUsed'], 10, 2);
        add_action('wp_umbrella_two_factor_disabled', [$this, 'onDisabled'], 10, 1);
        add_action('wp_umbrella_two_factor_reset', [$this, 'onReset'], 10, 2);
        add_action('wp_umbrella_two_factor_bypassed', [$this, 'onBypassed'], 10, 1);
        add_action('wp_umbrella_two_factor_policy_changed', [$this, 'onPolicyChanged'], 10, 1);
        add_action('wp_umbrella_two_factor_unreadable', [$this, 'onUnreadable'], 10, 1);
    }

    /**
     * @param bool $enabled
     *
     * @return void
     */
    public function onPolicyChanged($enabled = false)
    {
        $this->recorder->record(self::EVENT_POLICY_CHANGED, 'HIGH', [
            'kind' => 'two_factor',
            'outcome' => $enabled ? 'policy_enabled' : 'policy_disabled',
        ]);
    }

    /**
     * @param \WP_User|null $user
     *
     * @return void
     */
    public function onUnreadable($user = null)
    {
        $this->recorder->record(self::EVENT_UNREADABLE, 'HIGH', $this->userContext($user, [
            'kind' => 'two_factor',
            'outcome' => 'unreadable',
        ]));
    }

    /**
     * @param \WP_User|null $user
     *
     * @return void
     */
    public function onEnrolled($user = null)
    {
        $this->recorder->record(self::EVENT_ENROLLED, 'HIGH', $this->userContext($user, [
            'kind' => 'two_factor',
            'outcome' => 'enrolled',
        ]));
    }

    /**
     * @param \WP_User|null $user
     * @param string        $method
     *
     * @return void
     */
    public function onChallengeFailed($user = null, $method = 'totp')
    {
        $this->recorder->recordAggregated(
            self::EVENT_CHALLENGE_FAILED,
            'HIGH',
            $this->userContext($user, [
                'kind' => 'two_factor',
                'outcome' => 'failed',
                'method' => is_string($method) ? $method : 'totp',
            ]),
            self::FAILED_BUCKET_KEY,
            self::FAILED_WINDOW
        );
    }

    /**
     * @param \WP_User|null $user
     * @param int           $remaining
     *
     * @return void
     */
    public function onRecoveryCodeUsed($user = null, $remaining = 0)
    {
        $this->recorder->record(self::EVENT_RECOVERY_CODE_USED, 'HIGH', $this->userContext($user, [
            'kind' => 'two_factor',
            'outcome' => 'recovery_code_used',
            'remainingCodes' => (string) (int) $remaining,
        ]));
    }

    /**
     * @param \WP_User|null $user
     *
     * @return void
     */
    public function onDisabled($user = null)
    {
        $this->recorder->record(self::EVENT_DISABLED, 'HIGH', $this->userContext($user, [
            'kind' => 'two_factor',
            'outcome' => 'disabled',
        ]));
    }

    /**
     * @param \WP_User|null $user
     * @param \WP_User|null $actor
     *
     * @return void
     */
    public function onReset($user = null, $actor = null)
    {
        $context = $this->userContext($user, [
            'kind' => 'two_factor',
            'outcome' => 'reset',
        ]);

        if (is_object($actor) && !empty($actor->ID)) {
            $context['actorUserId'] = (int) $actor->ID;
            $context['actorUsername'] = isset($actor->user_login) ? (string) $actor->user_login : null;
        } else {
            $context['actorUserId'] = null;
            $context['actorUsername'] = null;
        }

        $this->recorder->record(self::EVENT_RESET, 'HIGH', $context);
    }

    /**
     * @param \WP_User|null $user
     *
     * @return void
     */
    public function onBypassed($user = null)
    {
        $this->recorder->record(self::EVENT_BYPASSED, 'HIGH', $this->userContext($user, [
            'kind' => 'two_factor',
            'outcome' => 'bypassed',
        ]));
    }

    /**
     * @param \WP_User|null $user
     * @param array         $context
     *
     * @return array
     */
    protected function userContext($user, array $context)
    {
        if (is_object($user) && !empty($user->ID)) {
            $context['targetUserId'] = (int) $user->ID;
            $context['targetUsername'] = isset($user->user_login) ? (string) $user->user_login : null;
            $context['targetUserRoles'] = isset($user->roles) && is_array($user->roles)
                ? array_values($user->roles)
                : [];
        }

        return $context;
    }
}
