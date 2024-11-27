<?php
namespace WPUmbrella\Services\Restore;

if (!defined('ABSPATH')) {
    exit;
}

class RestoreWordPressData
{
    public function writePluginRestoreData()
    {
        try {
            $upload_dir = wp_upload_dir();
            global $wpdb;

            $value = [
                'dbname' => DB_NAME,
                'user' => DB_USER,
                'password' => DB_PASSWORD,
                'charset' => DB_CHARSET,
                'host' => wp_umbrella_get_service('WordPressContext')->getDbHost(),
                'collate' => DB_COLLATE,
                'prefix' => $wpdb->prefix,
            ];

            $file = 'umbrella-restore.php';
            $current = "<?php

			require_once __DIR__ . '/umbrella-restore/index.php';
			";

            file_put_contents(sprintf('%s/%s', ABSPATH, $file), $current);

            $apiKey = wp_umbrella_get_api_key();
            $projectId = wp_umbrella_get_project_id();

            $file = 'security.php';
            $current = "<?php

define('TOKEN', '{$apiKey}');
define('PROJECT_ID', {$projectId});
			";

            file_put_contents(sprintf('%s/umbrella-restore/%s', ABSPATH, $file), $current);

            $file = 'security-database.php';
            $current = "<?php
define('DB_NAME', '{$value['dbname']}');
define('DB_USER', '{$value['user']}');
define('DB_PASSWORD', '{$value['password']}');
define('DB_CHARSET', '{$value['charset']}');
define('DB_COLLATE', '{$value['collate']}');
define('DB_HOST', '{$value['host']}');
define('DB_PREFIX', '{$value['prefix']}');
			";

            file_put_contents(sprintf('%s/umbrella-restore/%s', ABSPATH, $file), $current);

            return  [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error_code' => 'write_plugin_restore_data',
            ];
        }
    }

    public function getWordPressFiles()
    {
        $value = [
            'abspath' => null,
            'wp_content_dir' => null,
            'upload_dir' => null,
            'wp_plugin_dir' => null,
            'template_directory' => null,
        ];
        try {
            $upload_dir = wp_upload_dir();
            global $wpdb;

            return [
                'abspath' => ABSPATH,
                'wp_content_dir' => WP_CONTENT_DIR,
                'upload_dir' => $upload_dir['basedir'],
                'wp_plugin_dir' => WP_PLUGIN_DIR,
                'template_directory' => get_theme_root(get_template()),

            ];
        } catch (\Exception $e) {
            return $value;
        }
    }

    public function getWordPressDatabase()
    {
        $value = [
            'user' => null,
            'password' => null,
            'host' => null,
            'prefix' => null,
        ];

        try {
            global $wpdb;

            return [
                'dbname' => DB_NAME,
                'user' => DB_USER,
                'password' => DB_PASSWORD,
                'charset' => DB_CHARSET,
                'host' => wp_umbrella_get_service('WordPressContext')->getDbHost(),
                'collate' => DB_COLLATE,
                'prefix' => $wpdb->prefix,
            ];
        } catch (\Exception $e) {
            return $value;
        }
    }
}
