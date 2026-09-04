<?php
namespace WPUmbrella\Services;

if (!defined('ABSPATH')) {
    exit;
}

class HardeningSettings
{
    const OPTION_KEY = 'wp_umbrella_hardening_settings';

    const BLOCK_STATE_OPTION_KEY = 'wp_umbrella_hardening_htaccess_state';

    const NETWORK_TWO_FACTOR_OPTION_KEY = 'wp_umbrella_hardening_require_2fa_admin';

    const TWO_FACTOR_KEY = 'require_2fa_admin';

    protected $lastHtaccessResult = null;

    public function getLastHtaccessResult()
    {
        return $this->lastHtaccessResult;
    }

    public function getDefaultSettings()
    {
        return [
            'hide_wp_version' => false,
            'block_user_enumeration' => false,
            'mask_login_errors' => false,
            'disable_file_editor' => false,
            'security_headers' => false,
            'login_rate_limit' => false,
            'login_ip_blocklist' => false,
            'disable_file_mods' => false,
            'disable_xmlrpc' => false,
            'htaccess_umbrella_block' => false,
            'require_2fa_admin' => false,
        ];
    }

    public function getSettings()
    {
        $defaults = $this->getDefaultSettings();
        $settings = get_option(self::OPTION_KEY, []);

        if (!is_array($settings)) {
            $settings = [];
        }

        $settings = wp_parse_args($settings, $defaults);

        foreach ($defaults as $key => $value) {
            $settings[$key] = $this->castBoolean($settings[$key]);
        }

        $settings = array_intersect_key($settings, $defaults);

        if ($this->isNetwork()) {
            $networkValue = get_site_option(self::NETWORK_TWO_FACTOR_OPTION_KEY, null);

            if ($networkValue !== null) {
                $settings[self::TWO_FACTOR_KEY] = $this->castBoolean($networkValue);
            }
        }

        return $settings;
    }

    public function isEnabled($key)
    {
        $settings = $this->getSettings();

        return isset($settings[$key]) && $settings[$key];
    }

    /**
     * Settings stored network wide rather than per site.
     *
     * @return array
     */
    public function getNetworkScopedKeys()
    {
        if (!$this->isNetwork()) {
            return [];
        }

        return [self::TWO_FACTOR_KEY];
    }

    /**
     * @return bool
     */
    public function canEditNetworkScopedKeys()
    {
        if (!$this->isNetwork()) {
            return true;
        }

        if (current_user_can('manage_network_options')) {
            return true;
        }

        return get_current_user_id() === 0 && is_main_site();
    }

    public function updateSettings($params)
    {
        $params = (array) $params;

        if (!$this->canEditNetworkScopedKeys()) {
            $params = array_diff_key($params, array_flip($this->getNetworkScopedKeys()));
        }

        $settings = $this->getSettings();
        $previous = $settings;

        foreach (array_keys($this->getDefaultSettings()) as $key) {
            if (!isset($params[$key])) {
                continue;
            }

            $settings[$key] = $this->castBoolean($params[$key]);
        }

        // The block renders from the settings, so they have to be readable
        // before it is written. A failed write reverts its own option below.
        update_option(self::OPTION_KEY, $settings);

        if ($this->isNetwork() && isset($params[self::TWO_FACTOR_KEY])) {
            update_site_option(self::NETWORK_TWO_FACTOR_OPTION_KEY, $settings[self::TWO_FACTOR_KEY] ? 1 : 0);
        }

        $settings = $this->syncHtaccessUmbrellaBlock($previous, $settings);

        update_option(self::OPTION_KEY, $settings);

        $this->announceTwoFactorPolicyChange($previous, $settings);

        return $settings;
    }

    protected function announceTwoFactorPolicyChange($previous, $settings)
    {
        $before = isset($previous[self::TWO_FACTOR_KEY]) && $previous[self::TWO_FACTOR_KEY];
        $after = isset($settings[self::TWO_FACTOR_KEY]) && $settings[self::TWO_FACTOR_KEY];

        if ($before === $after) {
            return;
        }

        do_action('wp_umbrella_two_factor_policy_changed', $after);
    }

    protected function isNetwork()
    {
        return function_exists('is_multisite') && is_multisite();
    }

    protected function syncHtaccessUmbrellaBlock($previous, $settings)
    {
        $before = isset($previous['htaccess_umbrella_block']) && $previous['htaccess_umbrella_block'];
        $after = isset($settings['htaccess_umbrella_block']) && $settings['htaccess_umbrella_block'];

        // The block carries the security headers, so that option changing is
        // enough to make the file stale even when the block itself stays on.
        $headersChanged = $this->isSecurityHeadersEnabled($previous) !== $this->isSecurityHeadersEnabled($settings);

        if ($before === $after && !($after && $headersChanged)) {
            return $settings;
        }

        $htaccess = wp_umbrella_get_service('HtaccessFile');

        if (!$after) {
            $result = $htaccess->cleanUmbrellaBlock();
            $this->lastHtaccessResult = $result;

            if (isset($result['status']) && $result['status'] === 'error') {
                $settings['htaccess_umbrella_block'] = true;
            }

            return $settings;
        }

        $result = $htaccess->writeUmbrellaBlock();
        $this->lastHtaccessResult = $result;

        // "partial" means the root file was locked but the uploads block landed,
        // so the protection that matters most is on and the option has to stay
        // on with it.
        if (!$this->blockWasApplied($result)) {
            // A rewrite that failed left the previous block in place, so only an
            // activation gets reverted here.
            $settings['htaccess_umbrella_block'] = $before;
        }

        return $settings;
    }

    public function blockWasApplied($result)
    {
        return isset($result['status'])
            && ($result['status'] === 'ok' || $result['status'] === 'partial');
    }

    /**
     * @return array|null
     */
    public function getBlockState()
    {
        $state = get_option(self::BLOCK_STATE_OPTION_KEY, null);

        if (!is_array($state) || !isset($state['status'])) {
            return null;
        }

        return $state;
    }

    public function recordBlockState($result)
    {
        if (!$this->blockWasApplied($result)) {
            return;
        }

        $version = null;

        if ($result['status'] === 'ok') {
            $version = wp_umbrella_get_service('HtaccessFile')->getBlockVersion();
        }

        update_option(self::BLOCK_STATE_OPTION_KEY, [
            'status' => $result['status'],
            'version' => $version,
            'self_check' => isset($result['self_check']) ? $result['self_check'] : null,
            'updated_at' => time(),
        ], false);
    }

    public function clearBlockState()
    {
        delete_option(self::BLOCK_STATE_OPTION_KEY);
    }

    protected function isSecurityHeadersEnabled($settings)
    {
        return isset($settings['security_headers']) && $settings['security_headers'];
    }

    public function getStates()
    {
        $settings = $this->getSettings();

        $settings['file_editor_disabled'] = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;

        $state = $this->getBlockState();

        $settings['htaccess_umbrella_block_state'] = $this->blockStateLabel($settings, $state);
        $settings['htaccess_umbrella_block_self_check'] = isset($state['self_check']) ? $state['self_check'] : null;

        return $settings;
    }

    protected function blockStateLabel($settings, $state)
    {
        if (empty($settings['htaccess_umbrella_block'])) {
            return 'off';
        }

        if ($state === null) {
            return 'on';
        }

        return $state['status'] === 'partial' ? 'partial' : 'on';
    }

    protected function castBoolean($value)
    {
        if (is_string($value)) {
            return $value === 'true' || $value === '1';
        }

        return (bool) $value;
    }
}
