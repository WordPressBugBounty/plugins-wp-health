<?php
namespace WPUmbrella\Actions\Hardening;

use WP_Error;
use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;
use WPUmbrella\Actions\Hardening\LoginGuard\BloomFilter;
use WPUmbrella\Actions\Hardening\LoginGuard\FilterStorage;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class LoginIpBlocklist implements ExecuteHooks
{
    const KNOWN_GOOD_PREFIX = 'wp_umbrella_login_known_';

    const KNOWN_GOOD_TTL = 2592000;

    const FILTER_TTL = 604800;

    const ORACLE_TIMEOUT_MS = 800;

    protected $storage;

    public function __construct()
    {
        $this->storage = new FilterStorage();
    }

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('login_ip_blocklist')) {
            return;
        }

        add_filter('authenticate', [$this, 'enforce'], 25, 1);
        add_action('wp_login', [$this, 'onSuccess'], 10, 1);
    }

    public function enforce($user)
    {
        $ip = BloomFilter::canonicalizeIp(ClientIpResolver::resolve());

        if ($ip === null) {
            return $user;
        }

        if ($this->hasSuccessfulHistory($ip)) {
            return $user;
        }

        if (!$this->isBlocked($ip)) {
            return $user;
        }

        return new WP_Error(
            'wp_umbrella_login_ip_blocked',
            __('Access from your network is temporarily restricted.', 'wp-health')
        );
    }

    public function onSuccess($login)
    {
        $ip = BloomFilter::canonicalizeIp(ClientIpResolver::resolve());

        if ($ip === null) {
            return;
        }

        set_transient($this->knownGoodKey($ip), 1, self::KNOWN_GOOD_TTL);
    }

    protected function isBlocked($ip)
    {
        $blob = $this->resolveFilter();

        if ($blob === null) {
            return false;
        }

        return BloomFilter::isMember($blob, $ip);
    }

    protected function resolveFilter()
    {
        $fetchedAt = $this->storage->getFetchedAt();
        $blob = $this->storage->load();

        if ($blob !== null && (time() - $fetchedAt) < self::FILTER_TTL) {
            return $blob;
        }

        $fresh = $this->fetchFilter();

        if ($fresh === null) {
            return $blob;
        }

        return $fresh;
    }

    protected function fetchFilter()
    {
        $projectId = wp_umbrella_get_project_id();

        if (empty($projectId)) {
            return null;
        }

        $url = sprintf(
            '%s/v1/projects/%s/login-guard/filter',
            WP_UMBRELLA_NEW_API_URL,
            rawurlencode($projectId)
        );

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', wp_umbrella_get_outbound_bearer()),
                'X-Project' => site_url(),
                'X-Project-Id' => $projectId,
                'X-Secret-Token' => wp_umbrella_get_secret_token(),
            ],
            'timeout' => self::ORACLE_TIMEOUT_MS / 1000,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $blob = wp_remote_retrieve_body($response);

        if (!BloomFilter::isValidBlob($blob)) {
            return null;
        }

        if (!$this->storage->store($blob)) {
            return null;
        }

        $this->storage->markFetched();

        return $blob;
    }

    protected function hasSuccessfulHistory($ip)
    {
        return (bool) get_transient($this->knownGoodKey($ip));
    }

    protected function knownGoodKey($ip)
    {
        return self::KNOWN_GOOD_PREFIX . md5($ip);
    }
}
