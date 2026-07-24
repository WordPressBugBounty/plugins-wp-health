<?php
namespace WPUmbrella\Actions\Hardening;

use WP_Error;
use WP_User;
use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;
use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class LoginRateLimit implements ExecuteHooks
{
    const TRANSIENT_PREFIX = 'wp_umbrella_login_rl_';

    const MAX_FAILURES = 8;

    const WINDOW_MINUTES = 5;

    const BLOCK_EVENT_KEY = 'umbrella.protection.login_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_login_rl_block_bucket';

    const BLOCK_WINDOW = 1800;

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('login_rate_limit')) {
            return;
        }

        add_filter('authenticate', [$this, 'enforce'], 20, 2);
        add_action('wp_login_failed', [$this, 'onFailure'], 10, 1);
        add_action('wp_login', [$this, 'onSuccess'], 10, 1);

        add_action('init', function () {
            (new SyncScheduler())->schedule();
        }, 20);
    }

    public function enforce($user, $username = '')
    {
        $ip = ClientIpResolver::resolve();

        if ($ip === null) {
            return $user;
        }

        if ($this->getCount($ip) < self::MAX_FAILURES) {
            return $user;
        }

        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'login_rate_limit',
            'outcome' => 'blocked',
            'targetUsername' => is_string($username) && $username !== '' ? $username : null,
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);

        return new WP_Error(
            'wp_umbrella_login_rate_limited',
            __('Too many failed login attempts. Please try again later.', 'wp-health')
        );
    }

    public function onFailure($username)
    {
        $ip = ClientIpResolver::resolve();

        if ($ip === null) {
            return;
        }

        $count = $this->getCount($ip) + 1;

        set_transient($this->key($ip), $count, self::WINDOW_MINUTES * MINUTE_IN_SECONDS);
    }

    public function onSuccess($login)
    {
        $ip = ClientIpResolver::resolve();

        if ($ip === null) {
            return;
        }

        delete_transient($this->key($ip));
    }

    protected function getCount($ip)
    {
        $count = get_transient($this->key($ip));

        return is_numeric($count) ? (int) $count : 0;
    }

    protected function key($ip)
    {
        return self::TRANSIENT_PREFIX . md5($ip);
    }
}
