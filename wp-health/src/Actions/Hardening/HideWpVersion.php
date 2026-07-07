<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class HideWpVersion implements ExecuteHooks
{
    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('hide_wp_version')) {
            return;
        }

        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
    }
}
