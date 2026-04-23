<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class FilesystemCollector implements CollectorInterface
{
    public function getId()
    {
        return 'filesystem_permissions';
    }

    public function collect()
    {
        $paths = [
            'wordpress' => ABSPATH,
            'wp_content' => WP_CONTENT_DIR,
            'plugins' => WP_CONTENT_DIR . '/plugins',
            'themes' => WP_CONTENT_DIR . '/themes',
            'uploads' => WP_CONTENT_DIR . '/uploads',
            'wp_config' => ABSPATH . 'wp-config.php',
        ];

        $permissions = [];

        foreach ($paths as $label => $path) {
            if (!file_exists($path)) {
                $permissions[$label] = null;
                continue;
            }

            $perms = @fileperms($path);
            $permissions[$label] = $perms !== false ? decoct($perms & 0777) : null;
        }

        return $permissions;
    }
}
