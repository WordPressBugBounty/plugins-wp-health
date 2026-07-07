<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class SecurityHeaders implements ExecuteHooks
{
    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('security_headers')) {
            return;
        }

        add_filter('wp_headers', [$this, 'addSecurityHeaders']);
    }

    public function addSecurityHeaders($headers)
    {
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        return $headers;
    }
}
