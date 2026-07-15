<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class DisableFileMods implements ExecuteHooks
{
    const ALLOWED_CONTEXTS = [
        'install_plugin',
        'update_plugin',
        'install_plugins',
        'install_theme',
        'update_theme',
        'update_core',
        'capability_update_core',
        'capability_edit_themes',
        'automatic_updater',
        'can_install_language_pack',
        'download_language_pack',
    ];

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('disable_file_mods')) {
            return;
        }

        if (!defined('DISALLOW_FILE_MODS')) {
            define('DISALLOW_FILE_MODS', true);
        }

        add_filter('file_mod_allowed', [$this, 'allowUmbrellaFileMods'], 10, 2);
    }

    public function allowUmbrellaFileMods($allowed, $context)
    {
        if (!wp_umbrella_get_service('UpgradeContext')->isActive()) {
            return $allowed;
        }

        if (in_array($context, self::ALLOWED_CONTEXTS, true)) {
            return true;
        }

        return $allowed;
    }
}
