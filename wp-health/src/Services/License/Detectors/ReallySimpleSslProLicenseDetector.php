<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Really Simple SSL Pro
 *
 * Really Simple SSL Pro uses EDD Software Licensing.
 * The license key is stored via rsssl_get_option('license').
 */
class ReallySimpleSslProLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'really-simple-ssl-pro/really-simple-ssl-pro.php';
    protected $pluginName = 'Really Simple SSL Pro';
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

        $status = get_option('rsssl_license_status');

        if (!empty($status) && is_string($status)) {
            $status = $this->normalizeStatus($status);
        } else {
            $status = LicenseStatus::UNKNOWN;
        }

        return $this->buildLicenseData($licenseKey, $status);
    }

    /**
     * Find license key from possible storage locations
     *
     * @return string
     */
    private function findLicenseKey(): string
    {
        // Try the new options wrapper
        $options = get_option('rsssl_options');

        if (is_array($options) && !empty($options['license'])) {
            return $options['license'];
        }

        // Legacy option
        $key = get_option('rsssl_license_key');

        if (!empty($key) && is_string($key)) {
            return $key;
        }

        return '';
    }
}
