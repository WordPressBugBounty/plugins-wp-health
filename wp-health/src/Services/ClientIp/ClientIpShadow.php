<?php
namespace WPUmbrella\Services\ClientIp;

use WPUmbrella\Actions\ActivityLog\Framework\ClientIpResolver;

if (!defined('ABSPATH')) {
    exit;
}

class ClientIpShadow extends ClientIpResolver
{
    const NAME_SERVICE = 'ClientIpShadow';

    const OPTION = 'wp_umbrella_client_ip_shadow';

    const CLOUDFLARE_FRESHNESS = 2592000;

    const CLOUDFLARE_REFRESH = 86400;

    protected static $pending = [];

    protected static $flushScheduled = false;

    /**
     * @param string $path
     */
    public function observe($path)
    {
        try {
            $stats = $this->getData();
            $behindCloudflare = $this->isBehindCloudflare($stats);
            $current = self::resolve();
            $strict = $this->resolveStrict($behindCloudflare);
            $now = time();

            $events = ['observed'];

            if ($current !== $strict) {
                $events[] = 'diverged';
                $events[] = $this->divergenceType($current, $behindCloudflare);
            }

            if ($current !== null && !$this->isPublicIp($current)) {
                $events[] = 'shared_private_remote_addr';
            }

            $counters = isset(self::$pending[$path]) ? self::$pending[$path] : [];

            foreach ($events as $event) {
                $counters[$event] = (isset($counters[$event]) ? $counters[$event] : 0) + 1;
            }

            if ($current !== $strict) {
                $counters['last_divergence_at'] = $now;
            }

            self::$pending[$path] = $counters;

            if (!self::$flushScheduled && function_exists('add_action')) {
                self::$flushScheduled = true;
                add_action('shutdown', [$this, 'flush']);
            }
        } catch (\Throwable $e) {
        }
    }

    public function noteCloudflareAdminRequest()
    {
        if (empty($_SERVER['HTTP_CF_RAY']) || !self::matches(self::resolveRemoteAddr(), self::$cloudflareRanges)) {
            return;
        }

        $stats = $this->getData();
        $now = time();

        if (isset($stats['cloudflare_seen_at']) && $now - (int) $stats['cloudflare_seen_at'] < self::CLOUDFLARE_REFRESH) {
            return;
        }

        $stats['cloudflare_seen_at'] = $now;

        update_option(self::OPTION, $stats, false);
    }

    /**
     * @return array
     */
    public function flush()
    {
        if (empty(self::$pending)) {
            return;
        }

        try {
            $stats = $this->getData();

            if (!isset($stats['since'])) {
                $stats['since'] = time();
            }

            $stats['declared_proxies'] = count($this->declaredProxies());

            foreach (self::$pending as $path => $pending) {
                $counters = isset($stats[$path]) && is_array($stats[$path]) ? $stats[$path] : [];

                foreach ($pending as $event => $value) {
                    $counters[$event] = $event === 'last_divergence_at'
                        ? $value
                        : (isset($counters[$event]) ? (int) $counters[$event] : 0) + $value;
                }

                $stats[$path] = $counters;
            }

            self::$pending = [];

            update_option(self::OPTION, $stats, false);
        } catch (\Throwable $e) {
        }
    }

    public function getData()
    {
        $stats = get_option(self::OPTION, []);

        return is_array($stats) ? $stats : [];
    }

    /**
     * @param bool $behindCloudflare
     *
     * @return string|null
     */
    public function resolveStrict($behindCloudflare)
    {
        $remoteAddr = self::resolveRemoteAddr();

        if ($remoteAddr === null) {
            return null;
        }

        if ($behindCloudflare && self::matches($remoteAddr, self::$cloudflareRanges) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = self::extractFirstIp($_SERVER['HTTP_CF_CONNECTING_IP']);

            if ($ip !== null) {
                return $ip;
            }
        }

        $declared = $this->declaredProxies();

        if (!self::matches($remoteAddr, $declared)) {
            return $remoteAddr;
        }

        $hops = $behindCloudflare ? array_merge($declared, self::$cloudflareRanges) : $declared;

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $this->extractStrictForwardedFor($_SERVER['HTTP_X_FORWARDED_FOR'], $hops);

            if ($ip !== null) {
                return $ip;
            }
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = self::extractFirstIp($_SERVER['HTTP_X_REAL_IP']);

            if ($ip !== null) {
                return $ip;
            }
        }

        return $remoteAddr;
    }

    /**
     * @param array $stats
     *
     * @return bool
     */
    protected function isBehindCloudflare(array $stats)
    {
        if (empty($stats['cloudflare_seen_at'])) {
            return false;
        }

        return time() - (int) $stats['cloudflare_seen_at'] < self::CLOUDFLARE_FRESHNESS;
    }

    /**
     * @return string[]
     */
    protected function declaredProxies()
    {
        $trusted = apply_filters('wp_umbrella_trusted_proxies', self::$cloudflareRanges);

        if (!is_array($trusted)) {
            return [];
        }

        $declared = [];

        foreach ($trusted as $range) {
            if (is_string($range) && $range !== '' && !in_array($range, self::$cloudflareRanges, true)) {
                $declared[] = $range;
            }
        }

        return $declared;
    }

    /**
     * @param string|null $current
     * @param bool        $behindCloudflare
     *
     * @return string
     */
    protected function divergenceType($current, $behindCloudflare)
    {
        if (!self::matches(self::resolveRemoteAddr(), self::$cloudflareRanges)) {
            return 'other_divergence';
        }

        $cloudflareIp = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? self::extractFirstIp($_SERVER['HTTP_CF_CONNECTING_IP']) : null;

        if (!$behindCloudflare && $cloudflareIp !== null && $cloudflareIp === $current) {
            return 'cf_header_untrusted_site';
        }

        return 'xff_from_untrusted_peer';
    }

    /**
     * @param string   $value
     * @param string[] $hops
     *
     * @return string|null
     */
    protected function extractStrictForwardedFor($value, array $hops)
    {
        if (!is_string($value)) {
            return null;
        }

        foreach (array_reverse(explode(',', $value)) as $part) {
            $ip = self::sanitizeIp(trim($part));

            if ($ip === null) {
                return null;
            }

            if (!self::matches($ip, $hops)) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    protected function isPublicIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
