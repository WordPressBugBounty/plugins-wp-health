<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Services\TwoFactor\RecoveryCodes;
use WPUmbrella\Services\TwoFactor\SecretStore;
use WPUmbrella\Services\TwoFactor\TotpReplayGuard;
use WPUmbrella\Services\TwoFactor\TwoFactorPolicy;

if (!defined('ABSPATH')) {
    exit;
}

class ProfileSection implements ExecuteHooksBackend
{
    const ACTION_REGENERATE = 'wp_umbrella_2fa_regenerate_codes';

    const ACTION_DISABLE = 'wp_umbrella_2fa_disable';

    const NONCE = 'wp_umbrella_2fa_profile';

    const TRANSIENT_CODES = 'wp_umbrella_2fa_shown_codes_';

    const QUERY_CODES = 'wpu_2fa_codes';

    /**
     * @var TwoFactorPolicy
     */
    protected $policy;

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
        ?TwoFactorPolicy $policy = null,
        ?SecretStore $secretStore = null,
        ?RecoveryCodes $recoveryCodes = null,
        ?TotpReplayGuard $replayGuard = null
    ) {
        $this->policy = $policy !== null ? $policy : new TwoFactorPolicy();
        $this->secretStore = $secretStore !== null ? $secretStore : new SecretStore();
        $this->recoveryCodes = $recoveryCodes !== null ? $recoveryCodes : new RecoveryCodes();
        $this->replayGuard = $replayGuard !== null ? $replayGuard : new TotpReplayGuard();
    }

    public function hooks()
    {
        add_action('show_user_profile', [$this, 'render']);
        add_action('admin_post_' . self::ACTION_REGENERATE, [$this, 'handleRegenerate']);
        add_action('admin_post_' . self::ACTION_DISABLE, [$this, 'handleDisable']);
        add_action('admin_notices', [$this, 'renderLowCodesNotice']);
    }

    /**
     * @param \WP_User $user
     *
     * @return void
     */
    public function render($user)
    {
        if (!is_object($user) || (int) $user->ID !== get_current_user_id()) {
            return;
        }

        $enrolled = $this->secretStore->isEnrolled($user->ID);
        $remaining = $this->recoveryCodes->remaining($user->ID);
        $policyOn = $this->policy->isActive();
        $canDisable = $this->policy->canSelfDisable($user);
        $codes = $this->takeShownCodes($user->ID);

        include WP_UMBRELLA_TEMPLATES_TWO_FACTOR . '/profile-section.php';
    }

    /**
     * @param int $userId
     *
     * @return array|null
     */
    protected function takeShownCodes($userId)
    {
        $ticket = isset($_GET[self::QUERY_CODES]) ? (string) $_GET[self::QUERY_CODES] : '';

        if (!preg_match('/^[a-f0-9]{32}$/', $ticket)) {
            return null;
        }

        $key = self::TRANSIENT_CODES . (int) $userId . '_' . $ticket;
        $blob = get_transient($key);

        if (!is_string($blob) || $blob === '') {
            return null;
        }

        delete_transient($key);

        $plaintext = $this->secretStore->reveal($userId, $blob);

        if ($plaintext === null) {
            return null;
        }

        $codes = json_decode($plaintext, true);

        return is_array($codes) ? $codes : null;
    }

    /**
     * @return void
     */
    public function handleRegenerate()
    {
        $this->assertProfileRequest();

        $userId = get_current_user_id();

        if (!$this->secretStore->isEnrolled($userId)) {
            $this->bounceToProfile();
        }

        $codes = $this->recoveryCodes->regenerate($userId);
        $formatted = [];

        foreach ($codes as $code) {
            $formatted[] = $this->recoveryCodes->format($code);
        }

        $ticket = bin2hex(random_bytes(16));

        $blob = $this->secretStore->protect($userId, wp_json_encode($formatted));

        if ($blob !== null) {
            set_transient(self::TRANSIENT_CODES . $userId . '_' . $ticket, $blob, 300);
        }

        $this->bounceToProfile($ticket);
    }

    /**
     * @return void
     */
    public function handleDisable()
    {
        $this->assertProfileRequest();

        $userId = get_current_user_id();
        $user = get_user_by('id', $userId);

        if (!$this->policy->canSelfDisable($user)) {
            $this->bounceToProfile();
        }

        $this->secretStore->clear($userId);
        $this->recoveryCodes->clear($userId);
        $this->replayGuard->clear($userId);

        do_action('wp_umbrella_two_factor_disabled', $user);

        $this->bounceToProfile();
    }

    /**
     * @return void
     */
    public function renderLowCodesNotice()
    {
        $userId = get_current_user_id();

        if ($userId <= 0 || !$this->secretStore->isEnrolled($userId)) {
            return;
        }

        $remaining = $this->recoveryCodes->remaining($userId);

        if ($remaining >= RecoveryCodes::LOW_THRESHOLD) {
            return;
        }

        $class = $remaining === 0 ? 'notice notice-error' : 'notice notice-warning is-dismissible';

        $message = $remaining === 0
            ? __('You have no WP Umbrella recovery codes left. Generate a new set from your profile so you can still get in if you lose your phone.', 'wp-health')
            : sprintf(
                /* translators: %d: number of recovery codes left. */
                _n(
                    'You have %d WP Umbrella recovery code left. Generate a new set from your profile.',
                    'You have %d WP Umbrella recovery codes left. Generate a new set from your profile.',
                    $remaining,
                    'wp-health'
                ),
                $remaining
            );

        printf(
            '<div class="%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
            esc_attr($class),
            esc_html($message),
            esc_url(admin_url('profile.php#wp-umbrella-two-factor')),
            esc_html__('Go to my profile', 'wp-health')
        );
    }

    /**
     * @return void
     */
    protected function assertProfileRequest()
    {
        if (!is_user_logged_in()) {
            $this->bounceToProfile();
        }

        $nonce = isset($_POST['_wpnonce']) ? (string) $_POST['_wpnonce'] : '';

        if (!wp_verify_nonce($nonce, self::NONCE)) {
            $this->bounceToProfile();
        }
    }

    /**
     * @param string $ticket
     *
     * @return void
     */
    protected function bounceToProfile($ticket = '')
    {
        $url = admin_url('profile.php');

        if ($ticket !== '') {
            $url = add_query_arg(self::QUERY_CODES, $ticket, $url);
        }

        wp_safe_redirect($url . '#wp-umbrella-two-factor');
        exit;
    }
}
