<?php
namespace WPUmbrella\Services\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\PluginUpgrader;
use WPUmbrella\Models\RollbackSkin;

class Rollback
{
    const NAME_SERVICE = 'PluginRollback';

    /**
     *
     * @param array $args
     * @return void
     */
    public function rollback($args)
    {
        if (!isset($args['name'], $args['plugin_file'], $args['slug'], $args['version'])) {
            return;
        }

        wp_umbrella_get_service('ManagePlugin')->clearUpdates();

        $title = $args['name'];
        $nonce = 'upgrade-plugin_' . $args['slug'];
        $url = 'update.php?action=upgrade-plugin&plugin=' . urlencode($args['plugin_file']);
        $plugin = $args['slug'];
        $version = $args['version'];

        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            include_once ABSPATH . '/wp-admin/includes/admin.php';
            include_once ABSPATH . '/wp-admin/includes/plugin-install.php';
            include_once ABSPATH . '/wp-admin/includes/plugin.php';
            include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
            include_once ABSPATH . '/wp-admin/includes/class-plugin-upgrader.php';
        }

        @ob_start();
        $upgrader = new PluginUpgrader(new RollbackSkin(compact('title', 'nonce', 'url', 'plugin', 'version')));
        $data = $upgrader->rollback($args['plugin_file']);
        @ob_end_clean();

        return $data;
    }
}
