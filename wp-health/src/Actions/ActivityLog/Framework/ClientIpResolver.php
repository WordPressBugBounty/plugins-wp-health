<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Resolves the client IP for the current request.
 *
 * Handles common proxy headers (X-Forwarded-For, X-Real-IP, CF-Connecting-IP)
 * and validates that the value is a real IP. Only the first IP from a comma
 * separated list is considered.
 *
 * Returns null when no plausible IP can be determined.
 */
class ClientIpResolver
{
    /**
     * Headers to inspect, in priority order. The first one present wins.
     *
     * @var array
     */
    protected static $proxyHeaders = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
    ];

    /**
     * Resolves the client IP. Returns null if it cannot be determined.
     *
     * @return string|null
     */
    public static function resolve()
    {
        foreach (self::$proxyHeaders as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $ip = self::extractFirstIp($_SERVER[$header]);

            if ($ip !== null) {
                return $ip;
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = self::sanitizeIp($_SERVER['REMOTE_ADDR']);

            if ($ip !== null) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Extracts and validates the first IP from a comma separated list.
     *
     * @param string $value
     *
     * @return string|null
     */
    protected static function extractFirstIp($value)
    {
        if (!is_string($value)) {
            return null;
        }

        $parts = explode(',', $value);
        $first = isset($parts[0]) ? trim($parts[0]) : '';

        return self::sanitizeIp($first);
    }

    /**
     * Validates the IP. Returns the IP string when valid, null otherwise.
     *
     * @param string $ip
     *
     * @return string|null
     */
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
