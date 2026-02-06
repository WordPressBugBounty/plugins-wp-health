<?php
namespace WPUmbrella\Services\License\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for license detectors
 *
 * Each pro plugin license detector must implement this interface.
 */
interface LicenseDetectorInterface
{
    /**
     * Get the plugin slug this detector handles
     *
     * @return string Plugin slug (e.g., 'plugin-folder/plugin-file.php')
     */
    public function getPluginSlug(): string;

    /**
     * Get the plugin name for display
     *
     * @return string
     */
    public function getPluginName(): string;

    /**
     * Check if this detector supports the given plugin
     *
     * @param string $pluginSlug
     * @return bool
     */
    public function supports(string $pluginSlug): bool;

    /**
     * Detect and return license information
     *
     * @param array $options Detection options
     * @return array|null License data or null if no license found
     */
    public function detect(array $options = []): ?array;

    /**
     * Check if the plugin is installed
     *
     * @return bool
     */
    public function isInstalled(): bool;

    /**
     * Get plugin info when license is not found
     *
     * @return array
     */
    public function getNotFoundResponse(): array;
}
