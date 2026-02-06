<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\Contracts\LicenseDetectorInterface;
use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for license detectors
 */
abstract class AbstractLicenseDetector implements LicenseDetectorInterface
{
    /**
     * @var string
     */
    protected $pluginSlug;

    /**
     * @var string
     */
    protected $pluginName;

    /**
     * @var string
     */
    protected $licenseType = LicenseType::CUSTOM;

    /**
     * {@inheritdoc}
     */
    public function getPluginSlug(): string
    {
        return $this->pluginSlug;
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $pluginSlug): bool
    {
        return $this->pluginSlug === $pluginSlug;
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

        return isset($installedPlugins[$this->pluginSlug]);
    }

    /**
     * Check if plugin is active
     *
     * @return bool
     */
    protected function isActive(): bool
    {
        return is_plugin_active($this->pluginSlug);
    }

    /**
     * Build license data array
     *
     * @param string $licenseKey
     * @param string $status
     * @param array $extra
     * @return array
     */
    protected function buildLicenseData(
        string $licenseKey,
        string $status = LicenseStatus::UNKNOWN,
        array $extra = []
    ): array {
        return array_merge([
            'plugin_slug' => $this->pluginSlug,
            'plugin_name' => $this->pluginName,
            'license_key' => $licenseKey,
            'license_status' => $status,
            'license_type' => $this->licenseType,
            'license_expires' => null,
            'download_url' => '',
            'extra_data' => [],
        ], $extra);
    }

    /**
     * {@inheritdoc}
     */
    public function getNotFoundResponse(): array
    {
        return [
            'plugin_slug' => $this->pluginSlug,
            'plugin_name' => $this->pluginName,
            'license_key' => '',
            'license_status' => LicenseStatus::NOT_FOUND,
            'license_type' => $this->licenseType,
            'license_expires' => null,
            'download_url' => '',
            'extra_data' => [],
        ];
    }

    /**
     * Normalize status using LicenseStatus helper
     *
     * @param string $status
     * @return string
     */
    protected function normalizeStatus(string $status): string
    {
        return LicenseStatus::normalize($status);
    }
}
