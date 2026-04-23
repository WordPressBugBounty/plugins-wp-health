<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class AgentContextCollector implements CollectorInterface
{
    public function getId()
    {
        return 'agent_context';
    }

    public function collect()
    {
        $environmentType = function_exists('wp_get_environment_type')
            ? wp_get_environment_type()
            : $this->detectEnvironmentFromHostname();

        $hosting = $this->detectHostingProvider();
        $activePlugins = get_option('active_plugins', []);

        return [
            'environment_type' => $environmentType,
            'is_production' => $environmentType === 'production',
            'is_local' => $environmentType === 'local',
            'is_staging' => $environmentType === 'staging',
            'is_development' => $environmentType === 'development',
            'hosting_provider' => $hosting,
            'is_multisite' => is_multisite(),
            'has_woocommerce' => $this->hasActivePlugin($activePlugins, 'woocommerce/woocommerce.php'),
            'has_object_cache' => wp_using_ext_object_cache(),
            'active_plugin_count' => count($activePlugins),
            'severity_calibration' => $this->getSeverityCalibration($environmentType),
        ];
    }

    protected function detectEnvironmentFromHostname()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        $localPatterns = ['.local', '.test', '.dev', '.localhost', '127.0.0.1', '::1'];
        foreach ($localPatterns as $pattern) {
            if (strpos($host, $pattern) !== false) {
                return 'local';
            }
        }

        if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host)) {
            return 'local';
        }

        $stagingPrefixes = ['staging.', 'stage.', 'dev.', 'test.', 'preprod.'];
        foreach ($stagingPrefixes as $prefix) {
            if (strpos($host, $prefix) === 0) {
                return 'staging';
            }
        }

        return 'production';
    }

    protected function detectHostingProvider()
    {
        if (defined('IS_WPE') && IS_WPE) {
            return 'wpengine';
        }
        if (defined('KINSTAMU_VERSION')) {
            return 'kinsta';
        }
        if (defined('PANTHEON_ENVIRONMENT')) {
            return 'pantheon';
        }
        if (defined('IS_PRESSABLE') && IS_PRESSABLE) {
            return 'pressable';
        }
        if (defined('GD_SYSTEM_PLUGIN_DIR')) {
            return 'godaddy';
        }
        if (defined('IS_FLYWHEEL') && IS_FLYWHEEL) {
            return 'flywheel';
        }
        if (defined('STARTER_CONFIG')) {
            return 'developer_starter';
        }

        return 'unknown';
    }

    protected function hasActivePlugin($activePlugins, $pluginFile)
    {
        return in_array($pluginFile, $activePlugins);
    }

    protected function getSeverityCalibration($environmentType)
    {
        if ($environmentType === 'local' || $environmentType === 'development') {
            return [
                'no_https' => 'info',
                'debug_enabled' => 'info',
                'debug_display' => 'info',
                'file_editing' => 'info',
                'default_admin_user' => 'info',
                'inactive_plugins' => 'info',
                'note' => 'This is a development environment. Security and cleanup issues are expected and should be scored leniently.',
            ];
        }

        if ($environmentType === 'staging') {
            return [
                'no_https' => 'warning',
                'debug_enabled' => 'warning',
                'debug_display' => 'warning',
                'file_editing' => 'warning',
                'default_admin_user' => 'warning',
                'inactive_plugins' => 'warning',
                'note' => 'This is a staging environment. Issues should be flagged for production readiness but are not urgent.',
            ];
        }

        return [
            'no_https' => 'critical',
            'debug_enabled' => 'critical',
            'debug_display' => 'critical',
            'file_editing' => 'warning',
            'default_admin_user' => 'warning',
            'inactive_plugins' => 'warning',
            'note' => 'This is a production environment. Security issues are critical and must be addressed immediately.',
        ];
    }
}
