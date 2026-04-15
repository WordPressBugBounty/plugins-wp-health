<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Admin Columns Pro
 *
 * Admin Columns Pro stores its subscription key in `acp_subscription_key`
 * and activation details in `acp_subscription_details`.
 */
class AdminColumnsProLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'admin-columns-pro/admin-columns-pro.php';
    protected $pluginName = 'Admin Columns Pro';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $licenseKey = $this->findLicenseKey();

        if (empty($licenseKey)) {
            return null;
        }

        $details = get_option('acp_subscription_details');
        $status = LicenseStatus::UNKNOWN;
        $expires = null;

        if (is_array($details)) {
            $status = $this->normalizeStatus($details['status'] ?? 'unknown');
            $expiryTimestamp = $details['expiry_date'] ?? null;

            if (!empty($expiryTimestamp) && is_numeric($expiryTimestamp)) {
                $expires = date('Y-m-d H:i:s', (int) $expiryTimestamp);
            }
        }

        return $this->buildLicenseData($licenseKey, $status, [
            'license_expires' => $expires,
            'extra_data' => [
                'renewal_method' => $details['renewal_method'] ?? null,
            ],
        ]);
    }

    /**
     * Find license key from possible storage locations
     *
     * @return string
     */
    private function findLicenseKey(): string
    {
        // Constant override
        if (defined('ACP_LICENCE')) {
            return ACP_LICENCE;
        }

        $key = get_option('acp_subscription_key');

        if (!empty($key) && is_string($key)) {
            return $key;
        }

        return '';
    }
}
