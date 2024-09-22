<?php
namespace WPUmbrella\Services\Provider;

class UmbrellaInformations
{
    public function getDiagnosticData()
    {
        if (!function_exists('is_plugin_active') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Necessary for call rest_url(); with universal request
        wp_umbrella_get_service('WordPressContext')->requireWpRewrite();
        $restUrl = rest_url();

        return [
            'mu_plugins' => [
                'exist' => file_exists(WPMU_PLUGIN_DIR),
                'writable' => is_writable(dirname(WPMU_PLUGIN_DIR)),
                'exist_handler' => file_exists(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php')
            ],
            'memory_limit' => wp_umbrella_get_service('WordPressProvider')->getMemoryLimitBytes(),
            'ipv6' => wp_umbrella_get_service('ConnectSelfIpv6')->trySelfIpv6(),
        ];
    }

    public function getData()
    {
        if (!function_exists('is_plugin_active') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Necessary for call rest_url(); with universal request
        wp_umbrella_get_service('WordPressContext')->requireWpRewrite();
        $restUrl = rest_url();

        return [
            'site_url' => site_url(),
            'rest_url' => $restUrl,
            'home_url' => home_url(),
            'backdoor_url' => plugins_url(),
            'multisite' => is_multisite(),
            'abspath' => ABSPATH,
            'hosting' => wp_umbrella_get_service('HostResolver')->getCurrentHost(),
            'version' => WP_UMBRELLA_VERSION,
            'god_version' => WP_UMBRELLA_GOD_HANDLER_VERSION,
            'curl_exist' => function_exists('curl_init'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'class_exists_zip_archive' => class_exists('ZipArchive'),
            'mu_plugins' => [
                'exist' => file_exists(WPMU_PLUGIN_DIR),
                'writable' => is_writable(dirname(WPMU_PLUGIN_DIR)),
                'exist_handler' => file_exists(WPMU_PLUGIN_DIR . '/_WPHealthHandlerMU.php')
            ],
            'allow_issues_monitoring' => get_option('wp_health_allow_tracking'),
            'options' => wp_umbrella_get_options(),
            'memory_limit' => wp_umbrella_get_service('WordPressProvider')->getMemoryLimitBytes(),
        ];
    }
}
