<?php
namespace WPUmbrella\Actions\TwoFactor;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Services\TwoFactor\TwoFactorPolicy;

if (!defined('ABSPATH')) {
    exit;
}

class ConflictNotice implements ExecuteHooksBackend
{
    /**
     * @var TwoFactorPolicy
     */
    protected $policy;

    public function __construct(?TwoFactorPolicy $policy = null)
    {
        $this->policy = $policy !== null ? $policy : new TwoFactorPolicy();
    }

    const SCREENS = [
        'plugins',
        'plugins-network',
        'profile',
        'profile-network',
        'users',
        'users-network',
        'user-edit',
        'user-edit-network',
        'settings_page_wp-umbrella-settings',
        'toplevel_page_wp-umbrella-settings',
    ];

    public function hooks()
    {
        add_action('admin_notices', [$this, 'render']);
        add_action('network_admin_notices', [$this, 'render']);
    }

    /**
     * @return void
     */
    public function render()
    {
        if (!$this->isRelevantScreen()) {
            return;
        }

        $capability = is_network_admin() ? 'manage_network_options' : TwoFactorPolicy::CAPABILITY;

        if (!current_user_can($capability)) {
            return;
        }

        if (!$this->policy->isPolicyOn() || $this->policy->isEnforceable()) {
            return;
        }

        $conflict = $this->policy->getGuard()->getConflictingPlugin();

        if ($conflict === null) {
            return;
        }

        $whiteLabel = wp_umbrella_get_service('WhiteLabel')->getData();

        if (!empty($whiteLabel['hide_plugin'])) {
            return;
        }

        $name = empty($whiteLabel['plugin_name'])
            ? __('WP Umbrella', 'wp-health')
            : $whiteLabel['plugin_name'];

        $message = sprintf(
            /* translators: 1: name of this plugin. 2: name of the plugin that already handles two-factor authentication. */
            __(
                '%1$s two-factor authentication is turned on for this site but is not being applied, because %2$s already handles two-factor authentication. Deactivate it if you want %1$s to take over.',
                'wp-health'
            ),
            $name,
            $conflict
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>',
            esc_html($message)
        );
    }

    /**
     * Shown only where someone can act on it. An administrator does not need
     * this on every screen of their site, every time they load one.
     *
     * @return bool
     */
    protected function isRelevantScreen()
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        try {
            $screen = get_current_screen();
        } catch (\Throwable $exception) {
            return false;
        }

        if (!is_object($screen) || empty($screen->id)) {
            return false;
        }

        return in_array($screen->id, self::SCREENS, true);
    }
}
