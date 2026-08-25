<?php
namespace WPUmbrella\Actions\Hardening;

use WP_Error;
use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;
use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Actions\Hardening\AttackerIps\BloomFilter;
use WPUmbrella\Actions\Hardening\AttackerIps\CommunityIpFilter;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class LoginIpBlocklist implements ExecuteHooks
{
    const KNOWN_GOOD_PREFIX = 'wp_umbrella_login_known_';

    const KNOWN_GOOD_TTL = 2592000;

    const BLOCK_EVENT_KEY = 'umbrella.protection.login_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_login_block_bucket';

    const BLOCK_WINDOW = 1800;

    protected $filter;

    public function __construct(?CommunityIpFilter $filter = null)
    {
        $this->filter = $filter !== null ? $filter : new CommunityIpFilter();
    }

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('login_ip_blocklist')) {
            return;
        }

        add_filter('authenticate', [$this, 'enforce'], 25, 3);
        add_action('wp_login', [$this, 'onSuccess'], 10, 2);

        add_action('init', function () {
            (new SyncScheduler())->schedule();
        }, 20);
    }

    public function enforce($user, $username = '', $password = '')
    {
        $ip = BloomFilter::canonicalizeIp(ClientIpResolver::resolve());

        if ($ip === null) {
            return $user;
        }

        if ($this->hasSuccessfulHistory($ip, $this->normalizeIdentity($username))) {
            return $user;
        }

        if (!$this->filter->isBlocked($ip)) {
            return $user;
        }

        $this->recordBlock($username);

        return new WP_Error(
            'wp_umbrella_login_ip_blocked',
            __('Access from your network is temporarily restricted.', 'wp-health')
        );
    }

    protected function recordBlock($username)
    {
        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'login_ip_blocklist',
            'outcome' => 'blocked',
            'targetUsername' => is_string($username) && $username !== '' ? $username : null,
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);
    }

    /**
     * @param string        $login
     * @param \WP_User|null $user
     *
     * @return void
     */
    public function onSuccess($login, $user = null)
    {
        $ip = BloomFilter::canonicalizeIp(ClientIpResolver::resolve());

        if ($ip === null) {
            return;
        }

        foreach ($this->identitiesOf($login, $user) as $identity) {
            set_transient($this->knownGoodKey($ip, $identity), 1, self::KNOWN_GOOD_TTL);
        }
    }

    /**
     * @param string        $login
     * @param \WP_User|null $user
     *
     * @return array
     */
    protected function identitiesOf($login, $user)
    {
        $identities = [];

        if (is_string($login)) {
            $identities[] = $this->normalizeIdentity($login);
        }

        if (is_object($user) && isset($user->user_email) && is_string($user->user_email)) {
            $identities[] = $this->normalizeIdentity($user->user_email);
        }

        return array_unique(array_filter($identities));
    }

    protected function normalizeIdentity($identity)
    {
        return is_string($identity) ? strtolower(trim($identity)) : '';
    }

    protected function hasSuccessfulHistory($ip, $identity)
    {
        if ($identity === '') {
            return false;
        }

        return (bool) get_transient($this->knownGoodKey($ip, $identity));
    }

    protected function knownGoodKey($ip, $identity)
    {
        return self::KNOWN_GOOD_PREFIX . md5($ip . '|' . $identity);
    }
}
