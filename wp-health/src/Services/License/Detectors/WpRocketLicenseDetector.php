<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for WP Rocket
 */
class WpRocketLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'wp-rocket/wp-rocket.php';
    protected $pluginName = 'WP Rocket';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $settings = get_option('wp_rocket_settings');

        if (empty($settings) || !is_array($settings)) {
            return null;
        }

        $licenseKey = $settings['consumer_key'] ?? '';

        if (empty($licenseKey)) {
            return null;
        }

        // WP Rocket doesn't work without valid license
        return $this->buildLicenseData($licenseKey, LicenseStatus::VALID, [
            'extra_data' => [
                'consumer_email' => $settings['consumer_email'] ?? null,
            ],
        ]);
    }
}
