<?php
namespace WPUmbrella\Services\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes license data to ensure consistent structure
 */
class LicenseNormalizer
{
    /**
     * Normalize license data to ensure consistent structure
     *
     * @param array $license Raw license data
     * @return array Normalized license data
     */
    public function normalize(array $license): array
    {
        $defaults = [
            'plugin_slug' => '',
            'plugin_name' => '',
            'license_key' => '',
            'license_key_masked' => '',
            'license_status' => LicenseStatus::UNKNOWN,
            'license_expires' => null,
            'license_type' => LicenseType::CUSTOM,
            'download_url' => '',
            'is_active' => false,
            'extra_data' => [],
        ];

        $normalized = wp_parse_args($license, $defaults);

        // Generate masked license key if not provided
        if (!empty($normalized['license_key']) && empty($normalized['license_key_masked'])) {
            $normalized['license_key_masked'] = $this->maskLicenseKey($normalized['license_key']);
        }

        // Determine if license is active
        $normalized['is_active'] = LicenseStatus::isActive($normalized['license_status']);

        return $normalized;
    }

    /**
     * Mask a license key for secure display
     *
     * @param string $licenseKey
     * @return string
     */
    public function maskLicenseKey(string $licenseKey): string
    {
        if (empty($licenseKey)) {
            return '';
        }

        $length = strlen($licenseKey);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $visibleStart = substr($licenseKey, 0, 4);
        $visibleEnd = substr($licenseKey, -4);
        $maskedMiddle = str_repeat('*', min($length - 8, 16));

        return $visibleStart . $maskedMiddle . $visibleEnd;
    }
}
