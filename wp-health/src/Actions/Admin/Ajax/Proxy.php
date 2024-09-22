<?php

namespace WPUmbrella\Actions\Admin\Ajax;


use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class Proxy implements ExecuteHooksBackend
{
	protected $proxy;

    public function __construct()
    {
        $this->proxy = wp_umbrella_get_service('Proxy');
    }

    public function hooks()
    {
        add_action('wp_ajax_wp_health_proxy', [$this, 'proxy']);
    }

    public function proxy()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_health_proxy')) {
            wp_send_json_error([
                'code' => 'not_authorized',
            ]);
            exit;
        }

        $path = sanitize_text_field($_GET['path']);
        $query = isset($_GET['query']) ? $_GET['query'] : null;

        $data = $this->proxy->get($path, $query);

        wp_send_json_success($data);
    }
}
