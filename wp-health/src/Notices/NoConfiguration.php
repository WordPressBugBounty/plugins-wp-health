<?php

namespace WPUmbrella\Notices;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\AbstractNotice;

class NoConfiguration extends AbstractNotice
{
    /**
     * @static
     *
     * @return string
     */
    public static function get_template_file()
    {
        return WP_UMBRELLA_TEMPLATES_ADMIN_NOTICES . '/no-configuration.php';
    }
}
