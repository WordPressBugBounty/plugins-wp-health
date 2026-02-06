<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Advanced Custom Fields PRO
 */
class AcfProLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'advanced-custom-fields-pro/acf.php';
    protected $pluginName = 'Advanced Custom Fields PRO';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $licenseKey = get_option('acf_pro_license');

        if (empty($licenseKey)) {
            return null;
        }

        // ACF stores license as base64 encoded serialized array
        $licenseData = @unserialize(@base64_decode($licenseKey));

        if (!is_array($licenseData)) {
            return $this->buildLicenseData($licenseKey, LicenseStatus::UNKNOWN);
        }

        $key = $licenseData['key'] ?? $licenseKey;
        $status = !empty($licenseData['key']) ? LicenseStatus::VALID : LicenseStatus::UNKNOWN;

        return $this->buildLicenseData($key, $status, [
            'extra_data' => [
                'url' => $licenseData['url'] ?? '',
            ],
        ]);
    }
}
