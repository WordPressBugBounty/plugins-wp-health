<?php
namespace WPUmbrella\Services\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License status constants and normalization
 */
final class LicenseStatus
{
    const VALID = 'valid';
    const INVALID = 'invalid';
    const EXPIRED = 'expired';
    const DISABLED = 'disabled';
    const UNKNOWN = 'unknown';
    const NOT_FOUND = 'not_found';

    /**
     * Normalize various status strings to standardized status
     *
     * @param string $status
     * @return string
     */
    public static function normalize(string $status): string
    {
        $status = strtolower(trim($status));

        $validStatuses = ['valid', 'active', 'activated', 'ok', 'success', 'licensed'];
        $expiredStatuses = ['expired', 'expire'];
        $invalidStatuses = ['invalid', 'inactive', 'deactivated', 'failed', 'error'];
        $disabledStatuses = ['disabled', 'revoked', 'suspended'];

        if (in_array($status, $validStatuses, true)) {
            return self::VALID;
        }

        if (in_array($status, $expiredStatuses, true)) {
            return self::EXPIRED;
        }

        if (in_array($status, $invalidStatuses, true)) {
            return self::INVALID;
        }

        if (in_array($status, $disabledStatuses, true)) {
            return self::DISABLED;
        }

        return self::UNKNOWN;
    }

    /**
     * Check if a status represents an active license
     *
     * @param string $status
     * @return bool
     */
    public static function isActive(string $status): bool
    {
        return $status === self::VALID;
    }
}
