<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class BlockUserEnumeration implements ExecuteHooks
{
    const BLOCK_EVENT_KEY = 'umbrella.protection.user_enumeration_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_user_enum_block_bucket';

    const BLOCK_WINDOW = 1800;

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('block_user_enumeration')) {
            return;
        }

        add_action('init', [$this, 'blockAuthorEnumeration'], 1);
        add_filter('rest_endpoints', [$this, 'removeUsersRestEndpoints']);
        add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemapProvider'], 10, 2);

        add_action('init', function () {
            (new SyncScheduler())->schedule();
        }, 20);
    }

    public function blockAuthorEnumeration()
    {
        if (is_admin() || is_user_logged_in()) {
            return;
        }

        if (!isset($_GET['author'])) {
            return;
        }

        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'block_user_enumeration',
            'outcome' => 'blocked',
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);

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
