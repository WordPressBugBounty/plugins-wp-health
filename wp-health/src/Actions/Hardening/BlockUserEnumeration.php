<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class BlockUserEnumeration implements ExecuteHooks
{
    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('block_user_enumeration')) {
            return;
        }

        add_action('init', [$this, 'blockAuthorEnumeration'], 1);
        add_filter('rest_endpoints', [$this, 'removeUsersRestEndpoints']);
        add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemapProvider'], 10, 2);
    }

    public function blockAuthorEnumeration()
    {
        if (is_admin() || is_user_logged_in()) {
            return;
        }

        if (!isset($_GET['author'])) {
            return;
        }

        wp_safe_redirect(home_url(), 301);
        exit;
    }

    public function removeUsersRestEndpoints($endpoints)
    {
        if (is_user_logged_in()) {
            return $endpoints;
        }

        unset(
            $endpoints['/wp/v2/users'],
            $endpoints['/wp/v2/users/(?P<id>[\d]+)'],
            $endpoints['/wp/v2/users/me']
        );

        return $endpoints;
    }

    public function removeUsersSitemapProvider($provider, $name)
    {
        if ($name === 'users') {
            return false;
        }

        return $provider;
    }
}
