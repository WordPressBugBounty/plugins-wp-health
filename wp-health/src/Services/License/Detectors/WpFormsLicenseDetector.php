<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for WPForms Pro
 */
class WpFormsLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'wpforms/wpforms.php';
    protected $pluginName = 'WPForms Pro';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $license = get_option('wpforms_license');

        if (empty($license) || !is_array($license)) {
            return null;
        }

        $licenseKey = $license['key'] ?? '';

        if (empty($licenseKey)) {
            return null;
        }

        $status = $this->normalizeStatus($license['type'] ?? '');

        return $this->buildLicenseData($licenseKey, $status, [
            'extra_data' => [
                'license_type' => $license['type'] ?? null,
            ],
        ]);
    }
}
