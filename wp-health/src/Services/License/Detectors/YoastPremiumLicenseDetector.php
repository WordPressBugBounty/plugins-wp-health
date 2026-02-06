<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Yoast SEO Premium
 */
class YoastPremiumLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'wordpress-seo-premium/wp-seo-premium.php';
    protected $pluginName = 'Yoast SEO Premium';
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

        $licenseStatus = get_option('wpseo_premium_license_status');
        $status = $this->normalizeStatus($licenseStatus ?: '');

        return $this->buildLicenseData($licenseKey, $status);
    }

    /**
     * Find license key from various possible locations
     *
     * @return string
     */
    private function findLicenseKey(): string
    {
        // Yoast uses different option names based on version
        $possibleOptions = [
            'yoast_premium_license_key',
            'wpseo_premium_license_key',
        ];

        foreach ($possibleOptions as $optionName) {
            $value = get_option($optionName);
            if (!empty($value)) {
                return $value;
            }
        }

        // Try to get from site transients for newer versions
        $siteInfo = get_transient('yoast_premium_site_information');
        if ($siteInfo && isset($siteInfo['license_key'])) {
            return $siteInfo['license_key'];
        }

        return '';
    }
}
