<?php
namespace WPUmbrella\Services;

use WPUmbrella\Services\License\LicenseDetectorRegistry;
use WPUmbrella\Services\License\LicenseNormalizer;
use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\Contracts\LicenseDetectorInterface;
use WPUmbrella\Services\License\Detectors\AcfProLicenseDetector;
use WPUmbrella\Services\License\Detectors\ElementorProLicenseDetector;
use WPUmbrella\Services\License\Detectors\GravityFormsLicenseDetector;
use WPUmbrella\Services\License\Detectors\RankMathProLicenseDetector;
use WPUmbrella\Services\License\Detectors\SecuPressProLicenseDetector;
use WPUmbrella\Services\License\Detectors\WooCommerceExtensionLicenseDetector;
use WPUmbrella\Services\License\Detectors\WpFormsLicenseDetector;
use WPUmbrella\Services\License\Detectors\WpRocketLicenseDetector;
use WPUmbrella\Services\License\Detectors\YoastPremiumLicenseDetector;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LicenseManager Service
 *
 * Coordinates license detection using registered detectors.
 * Follows Single Responsibility Principle - only coordinates, doesn't detect.
 *
 * Usage for third-party plugin developers:
 *
 * 1. Create a detector class implementing LicenseDetectorInterface
 * 2. Register it via the filter:
 *
 * ```php
 * add_filter('wp_umbrella_license_detectors', function($registry) {
 *     $registry->register(new MyPluginLicenseDetector());
 *     return $registry;
 * });
 * ```
 *
 * Or add license data directly:
 *
 * ```php
 * add_filter('wp_umbrella_pro_plugin_licenses', function($licenses) {
 *     $licenses[] = [
 *         'plugin_slug' => 'my-plugin/my-plugin.php',
 *         'plugin_name' => 'My Plugin',
 *         'license_key' => get_option('my_license_key'),
 *         'license_status' => 'valid',
 *     ];
 *     return $licenses;
 * });
 * ```
 */
class LicenseManager
{
    const NAME_SERVICE = 'LicenseManager';

    /**
     * @var LicenseDetectorRegistry
     */
    private $registry;

    /**
     * @var LicenseNormalizer
     */
    private $normalizer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registry = new LicenseDetectorRegistry();
        $this->normalizer = new LicenseNormalizer();

        $this->registerBuiltInDetectors();
    }

    /**
     * Register built-in detectors
     *
     * @return void
     */
    private function registerBuiltInDetectors(): void
    {
        $builtInDetectors = [
            new AcfProLicenseDetector(),
            new ElementorProLicenseDetector(),
            new GravityFormsLicenseDetector(),
            new RankMathProLicenseDetector(),
            new SecuPressProLicenseDetector(),
            new WooCommerceExtensionLicenseDetector(),
            new WpFormsLicenseDetector(),
            new WpRocketLicenseDetector(),
            new YoastPremiumLicenseDetector(),
        ];

        $this->registry->registerMany($builtInDetectors);

        /**
         * Allow plugins to register their own detectors
         *
         * @param LicenseDetectorRegistry $registry
         */
        $this->registry = apply_filters('wp_umbrella_license_detectors', $this->registry);
    }

    /**
     * Get the detector registry
     *
     * @return LicenseDetectorRegistry
     */
    public function getRegistry(): LicenseDetectorRegistry
    {
        return $this->registry;
    }

    /**
     * Get all pro plugin licenses
     *
     * @param array $options Optional parameters:
     *                       - only_active: bool - Only return licenses for active plugins
     *                       - include_not_found: bool - Include plugins where license wasn't detected (default: true)
     * @return array
     */
    public function getAllLicenses(array $options = []): array
    {
        $licenses = [];
        $onlyActive = $options['only_active'] ?? false;
        $includeNotFound = $options['include_not_found'] ?? true;

        // Detect licenses from all registered detectors
        foreach ($this->registry->all() as $detector) {
            $license = $this->detectLicense($detector, $onlyActive, $includeNotFound);

            if ($license !== null) {
                $licenses[] = $license;
            }
        }

        /**
         * Filter to allow third-party plugins to add their license information directly
         *
         * @param array $licenses Current licenses array
         * @param array $options Options passed to getAllLicenses
         */
        $licenses = apply_filters('wp_umbrella_pro_plugin_licenses', $licenses, $options);

        // Normalize all licenses
        $licenses = array_map([$this->normalizer, 'normalize'], $licenses);

        // Filter out invalid entries
        $licenses = array_filter($licenses, function ($license) {
            return !empty($license['plugin_slug']);
        });

        return array_values($licenses);
    }

    /**
     * Detect license using a detector
     *
     * @param LicenseDetectorInterface $detector
     * @param bool $onlyActive
     * @param bool $includeNotFound Whether to include plugins with no license found
     * @return array|null
     */
    private function detectLicense(
        LicenseDetectorInterface $detector,
        bool $onlyActive,
        bool $includeNotFound = true
    ): ?array {
        // Check if plugin is installed
        if (!$detector->isInstalled()) {
            return null;
        }

        // If only_active option, check if plugin is active
        if ($onlyActive && !is_plugin_active($detector->getPluginSlug())) {
            return null;
        }

        try {
            $license = $detector->detect();

            // If no license found but plugin is installed, return not found response
            if ($license === null && $includeNotFound) {
                return $detector->getNotFoundResponse();
            }

            return $license;
        } catch (\Exception $e) {
            // Return not found response on error if plugin is installed
            if ($includeNotFound) {
                return $detector->getNotFoundResponse();
            }

            return null;
        }
    }

    /**
     * Get license for a specific plugin
     *
     * @param string $pluginSlug
     * @return array|null
     */
    public function getLicense(string $pluginSlug): ?array
    {
        $detector = $this->registry->get($pluginSlug);

        if ($detector === null) {
            // Try to find in the filtered licenses
            $allLicenses = $this->getAllLicenses();

            foreach ($allLicenses as $license) {
                if ($license['plugin_slug'] === $pluginSlug) {
                    return $license;
                }
            }

            return null;
        }

        $license = $this->detectLicense($detector, false);

        if ($license === null) {
            return null;
        }

        return $this->normalizer->normalize($license);
    }

    /**
     * Check if a plugin has a valid license
     *
     * @param string $pluginSlug
     * @return bool
     */
    public function hasValidLicense(string $pluginSlug): bool
    {
        $license = $this->getLicense($pluginSlug);

        if ($license === null) {
            return false;
        }

        return $license['license_status'] === LicenseStatus::VALID;
    }

    /**
     * Get summary of all licenses
     *
     * @return array
     */
    public function getLicensesSummary(): array
    {
        $licenses = $this->getAllLicenses();

        $summary = [
            'total' => count($licenses),
            'valid' => 0,
            'invalid' => 0,
            'expired' => 0,
            'not_found' => 0,
            'unknown' => 0,
        ];

        foreach ($licenses as $license) {
            switch ($license['license_status']) {
                case LicenseStatus::VALID:
                    $summary['valid']++;
                    break;
                case LicenseStatus::INVALID:
                case LicenseStatus::DISABLED:
                    $summary['invalid']++;
                    break;
                case LicenseStatus::EXPIRED:
                    $summary['expired']++;
                    break;
                case LicenseStatus::NOT_FOUND:
                    $summary['not_found']++;
                    break;
                default:
                    $summary['unknown']++;
                    break;
            }
        }

        return $summary;
    }
}
