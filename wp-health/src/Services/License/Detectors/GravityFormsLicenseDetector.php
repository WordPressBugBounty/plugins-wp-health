<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Gravity Forms
 */
class GravityFormsLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'gravityforms/gravityforms.php';
    protected $pluginName = 'Gravity Forms';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $licenseKey = get_option('rg_gforms_key');

        if (empty($licenseKey)) {
            return null;
        }

        $licenseInfo = get_option('gform_license_info');
        $status = LicenseStatus::UNKNOWN;

        if (is_array($licenseInfo) && isset($licenseInfo['is_valid'])) {
            $status = $licenseInfo['is_valid'] ? LicenseStatus::VALID : LicenseStatus::INVALID;
        }

        return $this->buildLicenseData($licenseKey, $status, [
            'license_expires' => $licenseInfo['date_expires'] ?? null,
            'extra_data' => [
                'license_level' => $licenseInfo['license_level'] ?? null,
            ],
        ]);
    }
}
