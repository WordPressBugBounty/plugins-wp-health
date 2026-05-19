<?php
namespace WPUmbrella\Services\Restore;

if (!defined('ABSPATH')) {
    exit;
}

class RestoreWordPressData
{
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
