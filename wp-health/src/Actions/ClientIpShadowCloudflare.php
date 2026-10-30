<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;
use WPUmbrella\Services\ClientIp\ClientIpShadow;

if (!defined('ABSPATH')) {
    exit;
}

class ClientIpShadowCloudflare implements ExecuteHooksBackend
{
    public function hooks()
    {
        add_action('admin_init', [$this, 'noteAdminRequest']);
    }

    public function noteAdminRequest()
    {
        if (empty($_SERVER['HTTP_CF_RAY']) || !current_user_can('manage_options')) {
            return;
        }

        (new ClientIpShadow())->noteCloudflareAdminRequest();
    }
}
