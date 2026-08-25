<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Services\TwoFactor\RecoveryCodes;
use WPUmbrella\Services\TwoFactor\SecretStore;
use WPUmbrella\Services\TwoFactor\TwoFactorPolicy;
use WPUmbrella\Services\TwoFactor\TwoFactorReset;

if (!defined('ABSPATH')) {
    exit;
}

class AdminReset implements ExecuteHooksBackend
{
    const ACTION = 'wp_umbrella_2fa_admin_reset';

    const NONCE = 'wp_umbrella_2fa_admin_reset_';

    const QUERY_DONE = 'wpu_2fa_reset';

    const CAPABILITY = 'edit_user';

    /**
     * Id shared by the button rendered in the profile screen and the form
     * rendered in the footer, which the HTML5 form attribute ties together.
     */
    const FORM_ID = 'wp-umbrella-2fa-admin-reset';

    /**
     * @var int
     */
    protected $pendingResetUserId = 0;

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
     * @var TwoFactorReset
     */
    protected $reset;

    public function __construct(
        ?TwoFactorPolicy $policy = null,
        ?SecretStore $secretStore = null,
        ?RecoveryCodes $recoveryCodes = null,
        ?TwoFactorReset $reset = null
    ) {
        $this->policy = $policy !== null ? $policy : new TwoFactorPolicy();
        $this->secretStore = $secretStore !== null ? $secretStore : new SecretStore();
        $this->recoveryCodes = $recoveryCodes !== null ? $recoveryCodes : new RecoveryCodes();
        $this->reset = $reset !== null ? $reset : new TwoFactorReset();
    }

    public function hooks()
    {
        add_action('edit_user_profile', [$this, 'render']);
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
        add_action('admin_notices', [$this, 'renderResetNotice']);
    }

    /**
     * @param \WP_User $user
     *
     * @return void
     */
    public function render($user)
    {
        if (!$this->canReset($user)) {
            return;
        }

        $enrolled = $this->secretStore->isEnrolled($user->ID);
        $remaining = $this->recoveryCodes->remaining($user->ID);
        $policyOn = $this->policy->isActive();
        $targetUserId = (int) $user->ID;
        $formId = self::FORM_ID;

        // edit_user_profile fires inside the profile <form>, and a nested form
        // is invalid HTML. The button stays here, the form goes to the footer.
        if ($enrolled) {
            $this->pendingResetUserId = $targetUserId;

            add_action('admin_footer', [$this, 'renderResetForm']);
        }

        include WP_UMBRELLA_TEMPLATES_TWO_FACTOR . '/admin-reset-section.php';
    }

    /**
     * @return void
     */
    public function renderResetForm()
    {
        if ($this->pendingResetUserId <= 0) {
            return;
        }

        $targetUserId = $this->pendingResetUserId;
        $nonceAction = self::NONCE . $targetUserId;
        $formId = self::FORM_ID;

        $this->pendingResetUserId = 0;

        include WP_UMBRELLA_TEMPLATES_TWO_FACTOR . '/admin-reset-form.php';
    }

    /**
     * @return void
     */
    public function handle()
    {
        $targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $nonce = isset($_POST['_wpnonce']) ? (string) $_POST['_wpnonce'] : '';

        if ($targetUserId <= 0 || !wp_verify_nonce($nonce, self::NONCE . $targetUserId)) {
            $this->refuse();
        }

        $user = get_user_by('id', $targetUserId);

        if (!$this->canReset($user)) {
            $this->refuse();
        }

        $this->reset->reset($user, wp_get_current_user());

        wp_safe_redirect(
            add_query_arg(
                [
                    'user_id' => $targetUserId,
                    self::QUERY_DONE => 'done',
                ],
                admin_url('user-edit.php')
            ) . '#wp-umbrella-two-factor'
        );
        exit;
    }

    /**
     * @return void
     */
    public function renderResetNotice()
    {
        $done = isset($_GET[self::QUERY_DONE]) ? (string) $_GET[self::QUERY_DONE] : '';
        $targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        if ($done !== 'done' || $targetUserId <= 0) {
            return;
        }

        if (!current_user_can(self::CAPABILITY, $targetUserId)) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Two-factor authentication has been reset for this user. They will be asked to set it up again the next time they log in.', 'wp-health')
        );
    }

    /**
     * @param \WP_User|false|null $user
     *
     * @return bool
     */
    protected function canReset($user)
    {
        if (!is_object($user) || empty($user->ID)) {
            return false;
        }

        if ((int) $user->ID === get_current_user_id()) {
            return false;
        }

        return current_user_can(self::CAPABILITY, (int) $user->ID);
    }

    /**
     * @return void
     */
    protected function refuse()
    {
        wp_die(
            esc_html__('You are not allowed to reset two-factor authentication for this user.', 'wp-health'),
            '',
            ['response' => 403]
        );
    }
}
