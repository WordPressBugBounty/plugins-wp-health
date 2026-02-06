<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for SecuPress Pro
 */
class SecuPressProLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'secupress-pro/secupress-pro.php';
    protected $pluginName = 'SecuPress Pro';
    protected $licenseType = LicenseType::EDD;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        if (!defined('SECUPRESS_PRO_VERSION')) {
            return null;
        }

        $consumerKey = get_site_option('secupress_pro_consumer_key');

        if (empty($consumerKey)) {
            return null;
        }

        $consumerEmail = get_site_option('secupress_pro_consumer_email');
        $licenseData = get_site_transient('secupress_pro_license_data');
        $status = LicenseStatus::UNKNOWN;

        if ($licenseData && isset($licenseData['license'])) {
            $status = $this->normalizeStatus($licenseData['license']);
        }

        return $this->buildLicenseData($consumerKey, $status, [
            'license_expires' => $licenseData['expires'] ?? null,
            'extra_data' => [
                'consumer_email' => $consumerEmail,
            ],
        ]);
    }
}
