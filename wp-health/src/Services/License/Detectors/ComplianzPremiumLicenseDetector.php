<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Complianz GDPR Premium
 *
 * Complianz uses EDD Software Licensing. The license key is stored
 * as a site option (multisite) or via cmplz_get_option() (single site).
 */
class ComplianzPremiumLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'complianz-gdpr-premium/complianz-gpdr-premium.php';
    protected $pluginName = 'Complianz GDPR Premium';
    protected $licenseType = LicenseType::EDD;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $licenseKey = $this->findLicenseKey();

        if (empty($licenseKey)) {
            return null;
        }

        $status = $this->getLicenseStatus($licenseKey);

        return $this->buildLicenseData($licenseKey, $status);
    }

    /**
     * Find license key from possible storage locations
     *
     * @return string
     */
    private function findLicenseKey(): string
    {
        // Constant override
        if (defined('CMPLZ_LICENSE_KEY')) {
            return CMPLZ_LICENSE_KEY;
        }

        // Multisite: stored as site option
        $key = get_site_option('cmplz_license_key');

        if (!empty($key) && is_string($key)) {
            return $key;
        }

        // Single site: stored in cmplz options
        $options = get_option('cmplz_options');

        if (is_array($options) && !empty($options['license'])) {
            return $options['license'];
        }

        return '';
    }

    /**
     * Get license status from stored data
     *
     * @param string $licenseKey
     * @return string
     */
    private function getLicenseStatus(string $licenseKey): string
    {
        $status = get_option('cmplz_license_status');

        if (!empty($status) && is_string($status)) {
            return $this->normalizeStatus($status);
        }

        // If key exists but no status, assume unknown
        return LicenseStatus::UNKNOWN;
    }
}
