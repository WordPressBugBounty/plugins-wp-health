<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for Crocoblock / JetPlugins ecosystem
 *
 * All JetPlugins (jet-engine, jet-blocks, jet-popup, jet-smart-filters, etc.)
 * share a centralized license stored in the `jet-license-data` option.
 */
class CrocoblockLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'jet-engine/jet-engine.php';
    protected $pluginName = 'Crocoblock';
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * All JetPlugin slugs covered by the Crocoblock license
     */
    private const JET_PLUGIN_SLUGS = [
        'jet-engine/jet-engine.php',
        'jet-blocks/jet-blocks.php',
        'jet-popup/jet-popup.php',
        'jet-smart-filters/jet-smart-filters.php',
        'jet-tabs/jet-tabs.php',
        'jet-appointments-booking/jet-appointments-booking.php',
        'jet-theme-core/jet-theme-core.php',
        'jet-elements/jet-elements.php',
        'jet-menu/jet-menu.php',
        'jet-tricks/jet-tricks.php',
        'jet-woo-builder/jet-woo-builder.php',
        'jet-blog/jet-blog.php',
        'jet-reviews/jet-reviews.php',
        'jet-compare-wishlist/jet-compare-wishlist.php',
        'jet-form-builder/jet-form-builder.php',
    ];

    /**
     * {@inheritdoc}
     */
    public function supports(string $pluginSlug): bool
    {
        return in_array($pluginSlug, self::JET_PLUGIN_SLUGS, true);
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

        foreach (self::JET_PLUGIN_SLUGS as $slug) {
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
        $licenseData = get_option('jet-license-data');

        if (empty($licenseData) || !is_array($licenseData)) {
            return null;
        }

        $licenseList = $licenseData['license-list'] ?? [];

        if (empty($licenseList) || !is_array($licenseList)) {
            return null;
        }

        // The license-list contains one or more license entries
        // Each entry has: licenseStatus, licenseDetails, plugins
        $firstLicense = reset($licenseList);
        $licenseKey = key($licenseList) ?: '';

        if (empty($firstLicense) || !is_array($firstLicense)) {
            return null;
        }

        $status = $this->normalizeStatus($firstLicense['licenseStatus'] ?? 'unknown');
        $details = $firstLicense['licenseDetails'] ?? [];
        $expires = $details['expire'] ?? null;
        $plugins = $firstLicense['plugins'] ?? [];

        return $this->buildLicenseData($licenseKey, $status, [
            'license_expires' => $expires,
            'extra_data' => [
                'plugins_count' => is_array($plugins) ? count($plugins) : 0,
                'site_url' => $details['site_url'] ?? null,
            ],
        ]);
    }
}
