<?php
namespace WPUmbrella\Services\License;

use WPUmbrella\Services\License\Contracts\LicenseDetectorInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry for license detectors
 *
 * Manages all registered license detectors and provides methods to add/retrieve them.
 */
class LicenseDetectorRegistry
{
    /**
     * @var LicenseDetectorInterface[]
     */
    private $detectors = [];

    /**
     * Register a detector
     *
     * @param LicenseDetectorInterface $detector
     * @return self
     */
    public function register(LicenseDetectorInterface $detector): self
    {
        $this->detectors[$detector->getPluginSlug()] = $detector;

        return $this;
    }

    /**
     * Register multiple detectors
     *
     * @param LicenseDetectorInterface[] $detectors
     * @return self
     */
    public function registerMany(array $detectors): self
    {
        foreach ($detectors as $detector) {
            $this->register($detector);
        }

        return $this;
    }

    /**
     * Get a detector by plugin slug
     *
     * @param string $pluginSlug
     * @return LicenseDetectorInterface|null
     */
    public function get(string $pluginSlug): ?LicenseDetectorInterface
    {
        return $this->detectors[$pluginSlug] ?? null;
    }

    /**
     * Get all registered detectors
     *
     * @return LicenseDetectorInterface[]
     */
    public function all(): array
    {
        return $this->detectors;
    }

    /**
     * Check if a detector is registered for a plugin
     *
     * @param string $pluginSlug
     * @return bool
     */
    public function has(string $pluginSlug): bool
    {
        return isset($this->detectors[$pluginSlug]);
    }

    /**
     * Remove a detector
     *
     * @param string $pluginSlug
     * @return self
     */
    public function remove(string $pluginSlug): self
    {
        unset($this->detectors[$pluginSlug]);

        return $this;
    }

    /**
     * Get count of registered detectors
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->detectors);
    }
}
