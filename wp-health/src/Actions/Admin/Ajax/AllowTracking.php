<?php
namespace WPUmbrella\Actions\Admin\Ajax;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class AllowTracking implements ExecuteHooksBackend
{
    public function hooks()
    {
        add_action('wp_ajax_wp_health_allow_tracking', [$this, 'allow']);
        add_action('wp_ajax_wp_health_disallow_tracking', [$this, 'disallow']);
    }

    public function allow()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_health_allow_tracking')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        update_option('wp_health_allow_tracking', true);

        wp_send_json_success();
    }

    public function disallow()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_health_disallow_tracking')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        update_option('wp_health_allow_tracking', false);

        wp_send_json_success();
    }
}
