<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class ServerEnvironmentCollector implements CollectorInterface
{
    public function getId()
    {
        return 'server_environment';
    }

    public function collect()
    {
        global $wpdb;

        $mysqlVersion = '';
        $mysqlClient = '';

        try {
            if ($wpdb && method_exists($wpdb, 'db_version')) {
                $mysqlVersion = $wpdb->db_version();
            }
            if ($wpdb && method_exists($wpdb, 'db_server_info')) {
                $mysqlClient = $wpdb->db_server_info();
            }
        } catch (\Exception $e) {
            // Database unreachable
        }

        $extensions = [
            'curl', 'dom', 'gd', 'imagick', 'json', 'mbstring',
            'openssl', 'xml', 'zip', 'intl', 'sodium', 'exif',
        ];

        $loadedExtensions = [];
        foreach ($extensions as $ext) {
            $loadedExtensions[$ext] = extension_loaded($ext);
        }

        return [
            'php_version' => phpversion(),
            'php_sapi' => php_sapi_name(),
            'web_server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
            'mysql_version' => $mysqlVersion,
            'mysql_client' => $mysqlClient,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'php_extensions' => $loadedExtensions,
        ];
    }
}
