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

        foreach (array_keys($this->getDefaultSettings()) as $key) {
            if (!isset($params[$key])) {
                continue;
            }

            $settings[$key] = $this->castBoolean($params[$key]);
        }

        update_option(self::OPTION_KEY, $settings);

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
