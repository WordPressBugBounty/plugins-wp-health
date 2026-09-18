<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class SecurityHeaders implements ExecuteHooks
{
    /**
     * Only upgrade-insecure-requests, which rewrites subresource URLs to https
     * and declares no fetch directive, so it stays harmless when a plugin sends
     * a policy of its own and the browser intersects the two.
     *
     * No Permissions-Policy here on purpose. Every feature worth denying backs
     * something a plugin legitimately uses: usb, serial and hid drive WooCommerce
     * POS hardware and receipt printers, accelerometer and gyroscope drive the
     * 360 viewers estate agents ship, geolocation drives store locators. A
     * default list would break those silently, on someone else's site, with
     * nothing in the page to attribute it to us.
     */
    const CONTENT_SECURITY_POLICY = 'upgrade-insecure-requests';

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
        $headers['Content-Security-Policy'] = self::CONTENT_SECURITY_POLICY;

        return $headers;
    }
}
