<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class SecurityCollector implements CollectorInterface
{
    public function getId()
    {
        return 'security';
    }

    public function collect()
    {
        return [
            'is_ssl' => is_ssl(),
            'https_enforced' => defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN,
            'file_editing_disabled' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
            'file_mods_disabled' => defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS,
            'display_errors' => ini_get('display_errors'),
            'debug_display_public' => (defined('WP_DEBUG') && WP_DEBUG) && (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY),
            'default_admin_user_exists' => username_exists('admin') !== false,
        ];
    }
}
