<?php
namespace WPUmbrella\Services;

if (!defined('ABSPATH')) {
    exit;
}

class HardeningSettings
{
    const OPTION_KEY = 'wp_umbrella_hardening_settings';

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

        return array_intersect_key($settings, $defaults);
    }

    public function isEnabled($key)
    {
        $settings = $this->getSettings();

        return isset($settings[$key]) && $settings[$key];
    }

    public function updateSettings($params)
    {
        $settings = $this->getSettings();
        $previous = $settings;

        foreach (array_keys($this->getDefaultSettings()) as $key) {
            if (!isset($params[$key])) {
                continue;
            }

            $settings[$key] = $this->castBoolean($params[$key]);
        }

        $settings = $this->syncHtaccessUmbrellaBlock($previous, $settings);

        update_option(self::OPTION_KEY, $settings);

        return $settings;
    }

    protected function syncHtaccessUmbrellaBlock($previous, $settings)
    {
        $before = isset($previous['htaccess_umbrella_block']) && $previous['htaccess_umbrella_block'];
        $after = isset($settings['htaccess_umbrella_block']) && $settings['htaccess_umbrella_block'];

        if ($before === $after) {
            return $settings;
        }

        $htaccess = wp_umbrella_get_service('HtaccessFile');

        if ($after) {
            $result = $htaccess->writeUmbrellaBlock();

            if (!isset($result['status']) || $result['status'] !== 'ok') {
                $settings['htaccess_umbrella_block'] = false;
            }

            return $settings;
        }

        $result = $htaccess->cleanUmbrellaBlock();

        if (isset($result['status']) && $result['status'] === 'error') {
            $settings['htaccess_umbrella_block'] = true;
        }

        return $settings;
    }

    public function getStates()
    {
        $settings = $this->getSettings();

        $settings['file_editor_disabled'] = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;

        return $settings;
    }

    protected function castBoolean($value)
    {
        if (is_string($value)) {
            return $value === 'true' || $value === '1';
        }

        return (bool) $value;
    }
}
