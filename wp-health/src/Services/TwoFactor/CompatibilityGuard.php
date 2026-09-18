<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class CompatibilityGuard
{
    const CONSTANT_DISABLE = 'WP_UMBRELLA_DISABLE_2FA';

    const WORDFENCE = 'Wordfence Login Security';

    const SOLID_SECURITY = 'Solid Security';

    const DEFENDER = 'Defender';

    const REALLY_SIMPLE_SECURITY = 'Really Simple Security';

    const KNOWN_CLASSES = [
        'Two_Factor_Core' => 'Two Factor',
        'WP2FA\\WP2FA' => 'WP 2FA',
        'WordfenceLS\\Controller_WordfenceLS' => self::WORDFENCE,
        'ITSEC_Two_Factor' => self::SOLID_SECURITY,
        'TwoFA\\Helper\\MoWpnsHandler' => 'miniOrange 2FA',
        'TwoFA\\Handler\\Mo2f_Main_Handler' => 'miniOrange 2FA',
        'TwoFA\\Onprem\\Mo2f_Main_Handler' => 'miniOrange 2FA',
        'MoWpnsHandler' => 'miniOrange 2FA',
        'Mo2f_Main_Handler' => 'miniOrange 2FA',
        'WP2FA' => 'WP 2FA',
        'WP_Defender\\Model\\Setting\\Two_Fa' => self::DEFENDER,
        'RSSSL\\Security\\WordPress\\Two_Fa\\Rsssl_Two_Factor' => self::REALLY_SIMPLE_SECURITY,
    ];

    const KNOWN_PLUGIN_FILES = [
        'two-factor/two-factor.php' => 'Two Factor',
        'wp-2fa/wp-2fa.php' => 'WP 2FA',
        'wordfence/wordfence.php' => self::WORDFENCE,
        'wordfence-login-security/wordfence-login-security.php' => self::WORDFENCE,
        'better-wp-security/better-wp-security.php' => self::SOLID_SECURITY,
        'ithemes-security-pro/ithemes-security-pro.php' => self::SOLID_SECURITY,
        'miniorange-2-factor-authentication/miniorange_2_factor_settings.php' => 'miniOrange 2FA',
        'defender-security/wp-defender.php' => self::DEFENDER,
        'really-simple-ssl/rlrsssl-really-simple-ssl.php' => self::REALLY_SIMPLE_SECURITY,
    ];

    /**
     * @var string|null
     */
    protected $conflict = null;

    /**
     * @var bool
     */
    protected $resolved = false;

    /**
     * @return bool
     */
    public function isEnforceable()
    {
        return $this->getBlockingReason() === null;
    }

    /**
     * @return string|null
     */
    public function getBlockingReason()
    {
        if (defined(self::CONSTANT_DISABLE) && constant(self::CONSTANT_DISABLE)) {
            return 'disabled_by_constant';
        }

        $conflict = $this->getConflictingPlugin();

        if ($conflict !== null) {
            return 'conflicting_plugin';
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getConflictingPlugin()
    {
        if ($this->resolved) {
            return $this->conflict;
        }

        $this->resolved = true;
        $this->conflict = $this->detectConflict();

        return $this->conflict;
    }

    /**
     * @return string|null
     */
    protected function detectConflict()
    {
        foreach ($this->getCandidates() as $label) {
            if ($this->isEnforcing($label)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    protected function getCandidates()
    {
        $labels = [];

        foreach (self::KNOWN_CLASSES as $className => $label) {
            if (class_exists($className, false)) {
                $labels[$label] = true;
            }
        }

        foreach ($this->getActivePlugins() as $pluginFile) {
            if (isset(self::KNOWN_PLUGIN_FILES[$pluginFile])) {
                $labels[self::KNOWN_PLUGIN_FILES[$pluginFile]] = true;
                continue;
            }

            $basename = basename($pluginFile);

            foreach (self::KNOWN_PLUGIN_FILES as $knownFile => $label) {
                if ($basename === basename($knownFile)) {
                    $labels[$label] = true;
                    break;
                }
            }
        }

        return array_keys($labels);
    }

    /**
     * A plugin is treated as enforcing as soon as it is there. The ones below
     * ship two factor as one feature among many, off until someone turns it on,
     * and expose a signal we could source and check, so they are asked for
     * their own state instead.
     *
     * Solid Security is deliberately not one of them: its module flag says the
     * module is on, not that anyone is ever challenged, and we have no way to
     * read enrolment there. Standing down for it costs a protection we could
     * have applied; getting it wrong costs the only one an administrator has.
     *
     * @param string $label
     *
     * @return bool
     */
    protected function isEnforcing($label)
    {
        try {
            switch ($label) {
                case self::WORDFENCE:
                    return $this->isWordfenceEnforcing();
                case self::DEFENDER:
                    return $this->isDefenderEnforcing();
                case self::REALLY_SIMPLE_SECURITY:
                    return $this->isReallySimpleSecurityEnforcing();
            }
        } catch (\Throwable $exception) {
            return true;
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function isWordfenceEnforcing()
    {
        if (!class_exists('\WordfenceLS\Controller_WordfenceLS', false)
            || !class_exists('\WordfenceLS\Controller_Settings', false)) {
            return true;
        }

        // Defining this one at all is enough for Wordfence, whatever its value.
        if (defined('WORDFENCE_LS_VERSIONONLY_MODE')) {
            return false;
        }

        if (defined('WORDFENCE_USE_LEGACY_2FA') && constant('WORDFENCE_USE_LEGACY_2FA')) {
            return false;
        }

        if ($this->isWordfenceRequiredForAdmins()) {
            return true;
        }

        return $this->hasWordfenceEnrolledUser();
    }

    /**
     * @return bool
     */
    protected function isWordfenceRequiredForAdmins()
    {
        $settings = \WordfenceLS\Controller_Settings::shared();

        if (!is_object($settings)
            || !method_exists($settings, 'get_required_2fa_role_activation_time')) {
            return true;
        }

        $roles = ['administrator'];

        if (function_exists('is_multisite') && is_multisite()) {
            $roles[] = 'super-admin';
        }

        foreach ($roles as $role) {
            if ($settings->get_required_2fa_role_activation_time($role) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nobody has to be required for someone to already be enrolled: the other
     * plugin challenges that account on its own, and a second challenge on top
     * is what locks a person out of their site.
     *
     * @return bool
     */
    protected function hasWordfenceEnrolledUser()
    {
        if (!class_exists('\WordfenceLS\Controller_Users', false)) {
            return true;
        }

        $users = \WordfenceLS\Controller_Users::shared();

        if (!is_object($users) || !method_exists($users, 'any_2fa_active')) {
            return true;
        }

        return (bool) $users->any_2fa_active();
    }

    /**
     * @return bool
     */
    protected function isDefenderEnforcing()
    {
        if (!function_exists('get_site_option')) {
            return true;
        }

        $settings = get_site_option('wd_2auth_settings');

        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        if (!is_array($settings) || !array_key_exists('enabled', $settings)) {
            return true;
        }

        if (!$settings['enabled']) {
            return false;
        }

        // Defender reads its own state as enabled plus at least one role, and
        // the role list ships empty.
        $roles = isset($settings['user_roles']) ? $settings['user_roles'] : null;

        if (!is_array($roles)) {
            return true;
        }

        return array_filter($roles) !== [];
    }

    /**
     * @return bool
     */
    protected function isReallySimpleSecurityEnforcing()
    {
        if (!function_exists('rsssl_get_option')) {
            return true;
        }

        if (!rsssl_get_option('login_protection_enabled')) {
            return false;
        }

        if (defined('RSSSL_DISABLE_2FA') && constant('RSSSL_DISABLE_2FA')) {
            return false;
        }

        if ($this->isReallySimpleSecuritySafeMode()) {
            return false;
        }

        return $this->hasReallySimpleSecurityRole();
    }

    /**
     * @return bool
     */
    protected function isReallySimpleSecuritySafeMode()
    {
        if (defined('RSSSL_SAFE_MODE') && constant('RSSSL_SAFE_MODE')) {
            return true;
        }

        if (!defined('WP_CONTENT_DIR')) {
            return false;
        }

        return file_exists(trailingslashit(WP_CONTENT_DIR) . 'rsssl-safe-mode.lock');
    }

    /**
     * The option can be on with no role picked, and the plugin then challenges
     * nobody. Every role list ships empty except the premium one.
     *
     * @return bool
     */
    protected function hasReallySimpleSecurityRole()
    {
        $options = [
            'two_fa_forced_roles',
            'two_fa_enabled_roles_email',
            'two_fa_enabled_roles_totp',
        ];

        foreach ($options as $option) {
            $roles = rsssl_get_option($option);

            if (is_array($roles) && array_filter($roles) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    protected function getActivePlugins()
    {
        $plugins = get_option('active_plugins', []);

        if (!is_array($plugins)) {
            $plugins = [];
        }

        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $networkPlugins = get_site_option('active_sitewide_plugins', []);

            if (is_array($networkPlugins)) {
                $plugins = array_merge($plugins, array_keys($networkPlugins));
            }
        }

        return array_values(array_filter($plugins, 'is_string'));
    }
}
