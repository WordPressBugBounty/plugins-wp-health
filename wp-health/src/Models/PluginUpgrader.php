<?php

namespace WPUmbrella\Models;

if (! defined('ABSPATH')) {
    exit;
}

class PluginUpgrader extends \Plugin_Upgrader
{
    public function rollback($plugin, $args = [])
    {
        $defaults    = array(
            'clear_update_cache' => true,
        );
        $parsed_args = wp_parse_args($args, $defaults);

        $this->init();
        $this->upgrade_strings();


        $url = sprintf('https://downloads.wordpress.org/plugin/%s.%s.zip',$this->skin->plugin, $this->skin->options['version']);

        add_filter('upgrader_pre_install', [$this, 'deactivate_plugin_before_upgrade' ], 10, 2);
        add_filter('upgrader_clear_destination', [$this, 'delete_old_plugin' ], 10, 4);

        $this->run([
            'package'           => $url,
            'destination'       => WP_PLUGIN_DIR,
            'clear_destination' => true,
            'clear_working'     => true,
            'hook_extra'        => array(
                'plugin' => $plugin,
                'type'   => 'plugin',
                'action' => 'update',
            ),
        ]);

        remove_filter('upgrader_pre_install', [$this, 'deactivate_plugin_before_upgrade']);
        remove_filter('upgrader_clear_destination', [$this, 'delete_old_plugin']);

        if (! $this->result || is_wp_error($this->result)) {
            return $this->result;
        }

        wp_clean_plugins_cache($parsed_args['clear_update_cache']);

        return true;
    }
}
