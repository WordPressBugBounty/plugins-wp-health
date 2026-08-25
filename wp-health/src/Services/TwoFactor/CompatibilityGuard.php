<?php
namespace WPUmbrella\Services\TwoFactor;

if (!defined('ABSPATH')) {
    exit;
}

class CompatibilityGuard
{
    const CONSTANT_DISABLE = 'WP_UMBRELLA_DISABLE_2FA';

    const KNOWN_CLASSES = [
        'Two_Factor_Core' => 'Two Factor',
        'WP2FA\\WP2FA' => 'WP 2FA',
        'WP2FA' => 'WP 2FA',
        'WordfenceLS\\Controller_WordfenceLS' => 'Wordfence Login Security',
        'ITSEC_Two_Factor' => 'Solid Security',
        'MoWpnsHandler' => 'miniOrange 2FA',
        'Mo2f_Main_Handler' => 'miniOrange 2FA',
    ];

    const KNOWN_PLUGIN_FILES = [
        'two-factor/two-factor.php' => 'Two Factor',
        'wp-2fa/wp-2fa.php' => 'WP 2FA',
        'wordfence-login-security/wordfence-login-security.php' => 'Wordfence Login Security',
        'better-wp-security/better-wp-security.php' => 'Solid Security',
        'ithemes-security-pro/ithemes-security-pro.php' => 'Solid Security',
        'miniorange-2-factor-authentication/miniorange_2_factor_settings.php' => 'miniOrange 2FA',
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
        foreach (self::KNOWN_CLASSES as $className => $label) {
            if (class_exists($className, false)) {
                return $label;
            }
        }

        foreach ($this->getActivePlugins() as $pluginFile) {
            if (isset(self::KNOWN_PLUGIN_FILES[$pluginFile])) {
                return self::KNOWN_PLUGIN_FILES[$pluginFile];
            }

            $basename = basename($pluginFile);

            foreach (self::KNOWN_PLUGIN_FILES as $knownFile => $label) {
                if ($basename === basename($knownFile)) {
                    return $label;
                }
            }
        }

        return null;
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
