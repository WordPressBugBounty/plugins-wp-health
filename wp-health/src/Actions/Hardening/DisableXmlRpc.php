<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class DisableXmlRpc implements ExecuteHooks
{
    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('disable_xmlrpc')) {
            return;
        }

        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', [$this, 'removePingbackMethods']);
    }

    public function removePingbackMethods($methods)
    {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);

        return $methods;
    }
}
