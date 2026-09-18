<?php
namespace WPUmbrella\Actions\Hardening;

use WP_Error;
use WP_User;
use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;
use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\Hardening\TransientCounter;

if (!defined('ABSPATH')) {
    exit;
}

class LoginRateLimit implements ExecuteHooks
{
    const TRANSIENT_PREFIX = 'wp_umbrella_login_rl_';

    const BLOCK_PREFIX = 'wp_umbrella_login_rl_lock_';

    const STRIKES_PREFIX = 'wp_umbrella_login_rl_strikes_';

    const BLOCK_INDEX_KEY = 'wp_umbrella_login_rl_blocked';

    const BLOCK_INDEX_MAX = 200;

    const MIN_FAILURES = 6;

    const MAX_FAILURES = 9;

    const MIN_WINDOW_MINUTES = 5;

    const MAX_WINDOW_MINUTES = 12;

    const STRIKES_TTL = 86400;

    const BLOCK_DURATIONS = [300, 1800, 14400];

    const BLOCK_EVENT_KEY = 'umbrella.protection.login_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_login_rl_block_bucket';

    const BLOCK_WINDOW = 1800;

    /**
     * @var TransientCounter
     */
    protected $counter;

    public function __construct()
    {
        $this->counter = new TransientCounter();
    }

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('login_rate_limit')) {
            return;
        }

        add_filter('authenticate', [$this, 'enforce'], 20, 2);
        add_action('wp_login_failed', [$this, 'onFailure'], 10, 1);

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

        if ($this->isBlocked($ip)) {
            return $this->deny($username);
        }

        return $user;
    }

    public function onFailure($username)
    {
        $ip = ClientIpResolver::resolve();

        if ($ip === null || $this->isBlocked($ip)) {
            return;
        }

        $count = $this->counter->increment($this->key($ip), $this->windowMinutes() * MINUTE_IN_SECONDS);

        if ($count < $this->maxFailures()) {
            return;
        }

        $this->block($ip);
        $this->recordBlockEvent($username);
    }

    protected function block($ip)
    {
        $strikes = $this->getStrikes($ip) + 1;

        set_transient($this->strikesKey($ip), $strikes, self::STRIKES_TTL);

        $durations = self::BLOCK_DURATIONS;
        $duration = $durations[min($strikes, count($durations)) - 1];

        set_transient($this->blockKey($ip), 1, $duration);

        $this->rememberBlock($ip, $duration);

        delete_transient($this->key($ip));
    }

    protected function isBlocked($ip)
    {
        return get_transient($this->blockKey($ip)) !== false;
    }

    protected function deny($username)
    {
        $this->recordBlockEvent($username);

        return new WP_Error(
            'wp_umbrella_login_rate_limited',
            __('Too many failed login attempts. Please try again later.', 'wp-health')
        );
    }

    protected function recordBlockEvent($username)
    {
        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'login_rate_limit',
            'outcome' => 'blocked',
            'targetUsername' => is_string($username) && $username !== '' ? $username : null,
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);
    }

    protected function maxFailures()
    {
        return self::MIN_FAILURES + $this->seed('failures') % (self::MAX_FAILURES - self::MIN_FAILURES + 1);
    }

    protected function windowMinutes()
    {
        return self::MIN_WINDOW_MINUTES + $this->seed('window') % (self::MAX_WINDOW_MINUTES - self::MIN_WINDOW_MINUTES + 1);
    }

    protected function seed($context)
    {
        return (int) hexdec(substr(md5(wp_salt('nonce') . $context), 0, 7));
    }

    protected function rememberBlock($ip, $duration)
    {
        $index = get_option(self::BLOCK_INDEX_KEY, []);

        if (!is_array($index)) {
            $index = [];
        }

        $now = time();

        foreach ($index as $hash => $expiresAt) {
            if (!is_numeric($expiresAt) || (int) $expiresAt <= $now) {
                unset($index[$hash]);
            }
        }

        $index[md5($ip)] = $now + $duration;

        if (count($index) > self::BLOCK_INDEX_MAX) {
            asort($index);
            $index = array_slice($index, -self::BLOCK_INDEX_MAX, null, true);
        }

        update_option(self::BLOCK_INDEX_KEY, $index, false);
    }

    protected function getStrikes($ip)
    {
        $strikes = get_transient($this->strikesKey($ip));

        return is_numeric($strikes) ? (int) $strikes : 0;
    }

    protected function key($ip)
    {
        return self::TRANSIENT_PREFIX . md5($ip);
    }

    protected function blockKey($ip)
    {
        return self::BLOCK_PREFIX . md5($ip);
    }

    protected function strikesKey($ip)
    {
        return self::STRIKES_PREFIX . md5($ip);
    }
}
