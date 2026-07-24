<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

class ClientIpResolver
{
    protected static $proxyHeaders = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
    ];

    protected static $cloudflareRanges = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public static function resolveRemoteAddr()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? self::sanitizeIp($_SERVER['REMOTE_ADDR']) : null;
    }

    public static function isBehindTrustedProxy()
    {
        return self::isTrustedProxy(self::resolveRemoteAddr());
    }

    public static function matches($ip, array $ranges)
    {
        if (!is_string($ip) || $ip === '') {
            return false;
        }

        foreach ($ranges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    public static function resolve()
    {
        $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? self::sanitizeIp($_SERVER['REMOTE_ADDR']) : null;

        if (self::isTrustedProxy($remoteAddr)) {
            foreach (self::$proxyHeaders as $header) {
                if (empty($_SERVER[$header])) {
                    continue;
                }

                $ip = self::extractFirstIp($_SERVER[$header]);

                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    protected static function isTrustedProxy($remoteAddr)
    {
        if ($remoteAddr === null) {
            return false;
        }

        $trusted = apply_filters('wp_umbrella_trusted_proxies', self::$cloudflareRanges);

        if (!is_array($trusted)) {
            return false;
        }

        foreach ($trusted as $range) {
            if (self::ipInRange($remoteAddr, $range)) {
                return true;
            }
        }

        return false;
    }

    protected static function ipInRange($ip, $range)
    {
        if (!is_string($range) || strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range, 2);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr(0xFF << (8 - $remainder) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }

    protected static function extractFirstIp($value)
    {
        if (!is_string($value)) {
            return null;
        }

        $parts = explode(',', $value);
        $first = isset($parts[0]) ? trim($parts[0]) : '';

        return self::sanitizeIp($first);
    }

    protected static function sanitizeIp($ip)
    {
        if (!is_string($ip) || $ip === '') {
            return null;
        }

        $filtered = filter_var($ip, FILTER_VALIDATE_IP);

        if ($filtered === false) {
            return null;
        }

        return $filtered;
    }
}
