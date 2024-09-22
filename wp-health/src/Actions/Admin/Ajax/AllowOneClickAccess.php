<?php

namespace WPUmbrella\Actions\Admin\Ajax;


use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class AllowOneClickAccess implements ExecuteHooksBackend
{
    public function hooks()
    {
        add_action('wp_ajax_wp_umbrella_allow_one_click_access', [$this, 'allow']);
        add_action('wp_ajax_wp_umbrella_disallow_one_click_access', [$this, 'disallow']);
    }

    public function allow()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_allow_one_click_access')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

		// If no options are set, so you can access
		delete_option('wp_umbrella_disallow_one_click_access');

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

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_umbrella_disallow_one_click_access')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        update_option('wp_umbrella_disallow_one_click_access', true);

        wp_send_json_success();
    }
}
