<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class DisableXmlRpc implements ExecuteHooks
{
    const BLOCK_EVENT_KEY = 'umbrella.protection.xmlrpc_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_xmlrpc_block_bucket';

    const BLOCK_WINDOW = 1800;

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('disable_xmlrpc')) {
            return;
        }

        add_filter('xmlrpc_enabled', [$this, 'disableAuthenticatedMethods']);
        add_filter('xmlrpc_methods', [$this, 'removePingbackMethods']);

        add_action('init', function () {
            (new SyncScheduler())->schedule();
        }, 20);
    }

    public function disableAuthenticatedMethods($enabled)
    {
        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'disable_xmlrpc',
            'outcome' => 'blocked',
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);

        return false;
    }

    public function removePingbackMethods($methods)
    {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);

        return $methods;
    }
}
