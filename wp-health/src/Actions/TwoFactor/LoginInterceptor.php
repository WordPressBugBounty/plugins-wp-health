<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\TwoFactor\LoginToken;
use WPUmbrella\Services\TwoFactor\RecoveryCodes;
use WPUmbrella\Services\TwoFactor\TwoFactorLoginFlow;

if (!defined('ABSPATH')) {
    exit;
}

class LoginInterceptor implements ExecuteHooks
{
    const ACTION_SETUP = 'wpu_2fa_setup';

    const ACTION_CHALLENGE = 'wpu_2fa';

    const NONCE_SETUP = 'wp_umbrella_2fa_setup';

    const NONCE_CHALLENGE = 'wp_umbrella_2fa_challenge';

    /**
     * @var bool
     */
    protected $completing = false;

    /**
     * @var string
     */
    protected $issuedSessionToken = '';

    /**
     * @var TwoFactorLoginFlow
     */
    protected $flow;

    /**
     * @var LoginToken
     */
    protected $loginToken;

    public function __construct(?TwoFactorLoginFlow $flow = null, ?LoginToken $loginToken = null)
    {
        $this->flow = $flow !== null ? $flow : new TwoFactorLoginFlow();
        $this->loginToken = $loginToken !== null ? $loginToken : new LoginToken();
    }

    public function hooks()
    {
        add_action('set_logged_in_cookie', [$this, 'rememberSessionToken'], 10, 6);
        add_action('wp_login', [$this, 'onLogin'], 5, 2);
        add_action('login_form_' . self::ACTION_SETUP, [$this, 'handleSetup']);
        add_action('login_form_' . self::ACTION_CHALLENGE, [$this, 'handleChallenge']);
    }

    /**
     * @param string $cookie
     * @param int    $expire
     * @param int    $expiration
     * @param int    $userId
     * @param string $scheme
     * @param string $token
     *
     * @return void
     */
    public function rememberSessionToken($cookie, $expire, $expiration, $userId, $scheme, $token = '')
    {
        $this->issuedSessionToken = is_string($token) ? $token : '';
    }

    /**
     * @param string        $userLogin
     * @param \WP_User|null $user
     *
     * @return void
     */
    public function onLogin($userLogin, $user = null)
    {
        if ($this->completing) {
            return;
        }

        $decision = $this->flow->decideOnLogin($user);

        if ($decision === TwoFactorLoginFlow::SKIP) {
            return;
        }

        $token = $this->loginToken->issue($user->ID, [
            'rememberme' => !empty($_POST['rememberme']),
            'redirectTo' => isset($_REQUEST['redirect_to']) ? (string) $_REQUEST['redirect_to'] : '',
        ]);

        $this->destroyIssuedSession($user->ID);

        wp_clear_auth_cookie();

        if ($decision === TwoFactorLoginFlow::SETUP) {
            $this->renderSetup($token, $user);
            exit;
        }

        $this->renderChallenge($token, null);
        exit;
    }

    /**
     * @return void
     */
    public function handleSetup()
    {
        list($token, $payload, $user) = $this->resolveRequest(self::NONCE_SETUP);

        $decision = $this->flow->decideSetup(
            $user,
            isset($_POST['wpu_2fa_step']) ? (string) $_POST['wpu_2fa_step'] : '',
            isset($payload['candidateSecret']) ? (string) $payload['candidateSecret'] : '',
            isset($_POST['wpu_2fa_code']) ? (string) $_POST['wpu_2fa_code'] : '',
            isset($payload['stage']) ? (string) $payload['stage'] : ''
        );

        if ($decision === TwoFactorLoginFlow::SKIP) {
            $this->bounceToLogin();
        }

        if ($decision === TwoFactorLoginFlow::COMPLETE) {
            $this->completeLogin($user, $token);
        }

        if ($decision === TwoFactorLoginFlow::CHALLENGE) {
            $this->renderChallenge($token, null);
            exit;
        }

        if ($decision === TwoFactorLoginFlow::ENROLLED) {
            do_action('wp_umbrella_two_factor_enrolled', $user);

            $this->loginToken->attach($token, 'stage', TwoFactorLoginFlow::STAGE_ENROLLED);

            $this->renderRecoveryCodes($token, $this->flow->enroll($user->ID));
            exit;
        }

        if ($decision === TwoFactorLoginFlow::SETUP_UNAVAILABLE) {
            $this->renderSetup($token, $user, __('Two-factor authentication could not be saved on this site. Contact your host or WP Umbrella support.', 'wp-health'));
            exit;
        }

        if ($decision === TwoFactorLoginFlow::SETUP_FAILED) {
            $this->renderSetup($token, $user, __('That code did not match. Check the time on your phone and try again.', 'wp-health'));
            exit;
        }

        $this->renderSetup($token, $user);
        exit;
    }

    /**
     * @return void
     */
    public function handleChallenge()
    {
        list($token, $payload, $user) = $this->resolveRequest(self::NONCE_CHALLENGE);

        $decision = $this->flow->decideChallenge(
            $user,
            isset($_POST['wpu_2fa_code']) ? (string) $_POST['wpu_2fa_code'] : '',
            isset($_POST['wpu_2fa_recovery_code']) ? (string) $_POST['wpu_2fa_recovery_code'] : ''
        );

        if ($decision === TwoFactorLoginFlow::SKIP) {
            $this->bounceToLogin();
        }

        if ($decision === TwoFactorLoginFlow::LOCKED) {
            $this->renderChallenge($token, __('Too many attempts. Wait a few minutes before trying again.', 'wp-health'));
            exit;
        }

        if ($decision === TwoFactorLoginFlow::SETUP) {
            $this->renderSetup($token, $user);
            exit;
        }

        if ($decision === TwoFactorLoginFlow::RECOVERED) {
            do_action(
                'wp_umbrella_two_factor_recovery_code_used',
                $user,
                $this->flow->remainingRecoveryCodes($user->ID)
            );

            $this->completeLogin($user, $token);
        }

        if ($decision === TwoFactorLoginFlow::CHALLENGE_FAILED_RECOVERY) {
            do_action('wp_umbrella_two_factor_challenge_failed', $user, 'recovery_code');

            $this->renderChallenge($token, __('That recovery code is not valid or has already been used.', 'wp-health'));
            exit;
        }

        if ($decision === TwoFactorLoginFlow::CHALLENGE_FAILED_TOTP) {
            do_action('wp_umbrella_two_factor_challenge_failed', $user, 'totp');

            $this->renderChallenge($token, __('That code is not valid.', 'wp-health'));
            exit;
        }

        if ($decision === TwoFactorLoginFlow::CHALLENGE_UNAVAILABLE) {
            do_action('wp_umbrella_two_factor_unreadable', $user);

            $this->renderChallenge($token, __('Your second factor cannot be read on this site right now. Use one of your recovery codes to log in, then set up your authenticator app again.', 'wp-health'));
            exit;
        }

        $this->completeLogin($user, $token);
    }

    /**
     * @param string $nonceAction
     *
     * @return array
     */
    protected function resolveRequest($nonceAction)
    {
        $token = isset($_POST['wpu_2fa_token']) ? (string) $_POST['wpu_2fa_token'] : '';
        $payload = $this->loginToken->peek($token);

        if ($payload === null) {
            $this->bounceToLogin();
        }

        $nonce = isset($_POST['wpu_2fa_nonce']) ? (string) $_POST['wpu_2fa_nonce'] : '';

        if (!hash_equals($this->formNonce($token, $nonceAction), $nonce)) {
            $this->bounceToLogin();
        }

        return [$token, $payload, get_user_by('id', $payload['userId'])];
    }

    /**
     * @param string $token
     * @param string $action
     *
     * @return string
     */
    protected function formNonce($token, $action)
    {
        return hash_hmac('sha256', $action . '|' . $token, wp_salt('nonce'));
    }

    /**
     * @param \WP_User $user
     * @param string   $token
     *
     * @return void
     */
    protected function completeLogin($user, $token)
    {
        $payload = $this->loginToken->consume($token);

        if ($payload === null) {
            $this->bounceToLogin();
        }

        $this->completing = true;

        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, !empty($payload['rememberme']));

        do_action('wp_login', $user->user_login, $user);

        $redirectTo = isset($payload['redirectTo']) ? (string) $payload['redirectTo'] : '';

        if ($redirectTo === '') {
            $redirectTo = admin_url();
        }

        wp_safe_redirect(apply_filters('login_redirect', $redirectTo, $redirectTo, $user));
        exit;
    }

    /**
     * The QR is drawn from the provisioning URI already printed in the page, so
     * the script adds no exposure. It is also optional: the setup key stays
     * readable when the file is blocked or the browser runs no JavaScript.
     *
     * @see login_enqueue_scripts
     *
     * @return void
     */
    public function enqueueQrCode()
    {
        wp_enqueue_script(
            'wp-umbrella-two-factor-qrcode',
            WP_UMBRELLA_DIRURL . 'app/javascripts/two-factor-qrcode.js',
            [],
            WP_UMBRELLA_VERSION,
            true
        );
    }

    /**
     * @param string        $token
     * @param \WP_User|null $user
     * @param string|null   $error
     *
     * @return void
     */
    protected function renderSetup($token, $user, $error = null)
    {
        $payload = $this->loginToken->peek($token);
        $candidate = isset($payload['candidateSecret']) ? (string) $payload['candidateSecret'] : '';

        if ($candidate === '') {
            $candidate = $this->flow->generateSecret();
            $this->loginToken->attach($token, 'candidateSecret', $candidate);
        }

        add_action('login_enqueue_scripts', [$this, 'enqueueQrCode']);

        $this->renderTemplate('setup.php', [
            'token' => $token,
            'error' => $error,
            'secret' => $candidate,
            'provisioningUri' => $this->flow->provisioningUri(
                $candidate,
                $user->user_login,
                $this->getIssuer()
            ),
            'action' => self::ACTION_SETUP,
            'nonce' => $this->formNonce($token, self::NONCE_SETUP),
        ]);
    }

    /**
     * @param string $token
     * @param array  $codes
     *
     * @return void
     */
    protected function renderRecoveryCodes($token, array $codes)
    {
        $recoveryCodes = new RecoveryCodes();
        $formatted = [];

        foreach ($codes as $code) {
            $formatted[] = $recoveryCodes->format($code);
        }

        $this->renderTemplate('recovery-codes.php', [
            'token' => $token,
            'codes' => $formatted,
            'action' => self::ACTION_SETUP,
            'nonce' => $this->formNonce($token, self::NONCE_SETUP),
        ]);
    }

    /**
     * @param string      $token
     * @param string|null $error
     *
     * @return void
     */
    protected function renderChallenge($token, $error = null)
    {
        $this->renderTemplate('challenge.php', [
            'token' => $token,
            'error' => $error,
            'action' => self::ACTION_CHALLENGE,
            'nonce' => $this->formNonce($token, self::NONCE_CHALLENGE),
        ]);
    }

    /**
     * @param string $template
     * @param array  $context
     *
     * @return void
     */
    protected function renderTemplate($template, array $context)
    {
        $wpUmbrellaTwoFactor = $context;

        if (function_exists('login_header')) {
            login_header(__('Two-factor authentication', 'wp-health'));
        }

        include WP_UMBRELLA_TEMPLATES_TWO_FACTOR . '/' . $template;

        if (function_exists('login_footer')) {
            login_footer();
        }
    }

    /**
     * @return string
     */
    protected function getIssuer()
    {
        $name = get_bloginfo('name');

        if (!is_string($name) || trim($name) === '') {
            $name = wp_parse_url(home_url(), PHP_URL_HOST);
        }

        return is_string($name) && $name !== '' ? $name : 'WordPress';
    }

    /**
     * @param int $userId
     *
     * @return void
     */
    protected function destroyIssuedSession($userId)
    {
        if ($this->issuedSessionToken === '' || !class_exists('WP_Session_Tokens')) {
            return;
        }

        \WP_Session_Tokens::get_instance((int) $userId)->destroy($this->issuedSessionToken);

        $this->issuedSessionToken = '';
    }

    /**
     * @return void
     */
    protected function bounceToLogin()
    {
        wp_safe_redirect(wp_login_url());
        exit;
    }
}
