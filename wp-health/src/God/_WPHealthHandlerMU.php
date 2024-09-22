<?php

if (file_exists(WP_PLUGIN_DIR . '/wp-health')) {
    include_once WP_PLUGIN_DIR . '/wp-health/src/Helpers/GodTransient.php';
    include_once WP_PLUGIN_DIR . '/wp-health/src/God/ErrorHandler.php';

    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (class_exists("\WPUmbrella\God\ErrorHandler") &&
       is_plugin_active('wp-health/wp-health.php')) {
        $god = new \WPUmbrella\God\ErrorHandler();
        $god->init();
    }
}
