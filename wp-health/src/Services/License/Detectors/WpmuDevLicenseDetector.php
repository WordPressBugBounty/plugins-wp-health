<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for WPMU DEV ecosystem
 *
 * WPMU DEV plugins (Smush Pro, SmartCrawl Pro, etc.) share a centralized
 * license via the WPMU DEV Dashboard plugin. The API key is stored in
 * `wpmudev_apikey` site option, membership data in `wdp_un_membership_data`.
 */
class WpmuDevLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'wpmudev-updates/update-notifications.php';
    protected $pluginName = 'WPMU DEV';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * WPMU DEV plugin slugs covered by the membership
     */
    private const WPMUDEV_PLUGIN_SLUGS = [
        'wpmudev-updates/update-notifications.php',
        'wp-smush-pro/wp-smush.php',
        'wpmu-dev-seo/wpmu-dev-seo.php',
        'wp-defender/wp-defender.php',
        'wp-hummingbird/wp-hummingbird.php',
        'forminator/forminator.php',
        'hustle/opt-in.php',
        'beehive-analytics/beehive-analytics.php',
        'snapshot/snapshot.php',
        'branda-white-labeling/ultimate-branding.php',
        'shipper/shipper.php',
    ];

    /**
     * {@inheritdoc}
     */
    public function supports(string $pluginSlug): bool
    {
        return in_array($pluginSlug, self::WPMUDEV_PLUGIN_SLUGS, true);
    }

    /**
     * {@inheritdoc}
     */
    public function isInstalled(): bool
    {
        if (defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installedPlugins = get_plugins();

        foreach (self::WPMUDEV_PLUGIN_SLUGS as $slug) {
            if (isset($installedPlugins[$slug])) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        $apiKey = $this->findApiKey();

        if (empty($apiKey)) {
            return null;
        }

        $membershipData = get_site_option('wdp_un_membership_data');
        $status = LicenseStatus::UNKNOWN;

        if (is_array($membershipData) && isset($membershipData['membership'])) {
            $membership = $membershipData['membership'];
            // WPMU DEV uses membership levels: 'full', 'single', 'free', 'expired', etc.
            if (in_array($membership, ['full', 'single', 'unit'], true)) {
                $status = LicenseStatus::VALID;
            } elseif ($membership === 'expired') {
                $status = LicenseStatus::EXPIRED;
            } elseif ($membership === 'free') {
                $status = LicenseStatus::INVALID;
            } else {
                $status = $this->normalizeStatus($membership);
            }
        }

        return $this->buildLicenseData($apiKey, $status, [
            'extra_data' => [
                'membership_type' => $membershipData['membership'] ?? null,
            ],
        ]);
    }

    /**
     * Find API key from possible storage locations
     *
     * @return string
     */
    private function findApiKey(): string
    {
        if (defined('WPMUDEV_APIKEY')) {
            return WPMUDEV_APIKEY;
        }

        $key = get_site_option('wpmudev_apikey');

        if (!empty($key) && is_string($key)) {
            return $key;
        }

        return '';
    }
}
