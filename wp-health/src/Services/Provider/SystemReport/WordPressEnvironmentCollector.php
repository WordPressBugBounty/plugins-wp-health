<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class WordPressEnvironmentCollector implements CollectorInterface
{
    public function getId()
    {
        return 'wordpress_environment';
    }

    public function collect()
    {
        return [
            'version' => get_bloginfo('version'),
            'site_url' => site_url(),
            'home_url' => home_url(),
            'is_multisite' => is_multisite(),
            'memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '',
            'max_memory_limit' => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : '',
            'debug_mode' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'has_object_cache' => wp_using_ext_object_cache(),
            'permalink_structure' => get_option('permalink_structure'),
            'environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : null,
            'language' => get_locale(),
            'timezone' => $this->getTimezone(),
            'is_ssl' => is_ssl(),
        ];
    }

    protected function getTimezone()
    {
        $timezone = get_option('timezone_string');

        if (!empty($timezone)) {
            return $timezone;
        }

        if (function_exists('wp_timezone_string')) {
            return wp_timezone_string();
        }

        return '';
    }
}
