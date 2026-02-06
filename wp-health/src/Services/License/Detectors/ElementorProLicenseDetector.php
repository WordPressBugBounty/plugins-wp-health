<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Elementor Pro
 */
class ElementorProLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'elementor-pro/elementor-pro.php';
    protected $pluginName = 'Elementor Pro';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * Possible option names for Elementor Pro license key
     */
    private const LICENSE_KEY_OPTIONS = [
        'elementor_pro_license_key',
        'elementor_license_key',
        '_elementor_pro_license_key',
    ];

    /**
     * Possible option names for Elementor Pro license data
     */
    private const LICENSE_DATA_OPTIONS = [
        'elementor_pro_license_data',
        'elementor_license_data',
        '_elementor_pro_license_data',
    ];

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $licenseKey = $this->findLicenseKey();

        if (empty($licenseKey)) {
            return null;
        }

        $licenseData = $this->findLicenseData();
        $status = LicenseStatus::UNKNOWN;

        if ($licenseData !== null) {
            // License data can be object or array
            if (is_object($licenseData) && isset($licenseData->license)) {
                $status = $this->normalizeStatus($licenseData->license);
            } elseif (is_array($licenseData) && isset($licenseData['license'])) {
                $status = $this->normalizeStatus($licenseData['license']);
            }
        }

        $expires = null;
        $customerName = null;
        $features = [];

        if (is_object($licenseData)) {
            $expires = $licenseData->expires ?? null;
            $customerName = $licenseData->customer_name ?? null;
            $features = $licenseData->features ?? [];
        } elseif (is_array($licenseData)) {
            $expires = $licenseData['expires'] ?? null;
            $customerName = $licenseData['customer_name'] ?? null;
            $features = $licenseData['features'] ?? [];
        }

        return $this->buildLicenseData($licenseKey, $status, [
            'license_expires' => $expires,
            'extra_data' => [
                'license_name' => $customerName,
                'features' => $features,
            ],
        ]);
    }

    /**
     * Find license key from possible option names
     *
     * @return string
     */
    private function findLicenseKey(): string
    {
        foreach (self::LICENSE_KEY_OPTIONS as $optionName) {
            $value = get_option($optionName);
            if (!empty($value) && is_string($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Find license data from possible option names
     *
     * @return object|array|null
     */
    private function findLicenseData()
    {
        foreach (self::LICENSE_DATA_OPTIONS as $optionName) {
            $value = get_option($optionName);
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }
}
