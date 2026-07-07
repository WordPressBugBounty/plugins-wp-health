<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures WordPress 7.0 AI configuration changes.
 *
 * Event keys emitted:
 * - ai.connector.connected       (MEDIUM)
 * - ai.connector.disconnected    (MEDIUM)
 * - ai.master_switch.enabled     (MEDIUM)
 * - ai.master_switch.disabled    (MEDIUM)
 * - ai.feature.enabled           (MEDIUM)
 * - ai.feature.disabled          (MEDIUM)
 *
 * Everything is option driven, so we bridge the four option hooks and route
 * each write to the right event from the option name:
 * - connectors_ai_<slug>_api_key : a core AI connector. Connected when the key
 *   goes from empty to set, disconnected when it goes from set to empty.
 * - wpai_features_enabled        : the AI plugin master switch (boolean).
 * - wpai_feature_<slug>_enabled  : a single AI plugin feature toggle (boolean).
 *
 * delete_option fires before the value is gone, so we snapshot it there and
 * emit on deleted_option, mirroring PluginSensor's deletion handling.
 */
class AiConnectorSensor extends AbstractSensor
{
    const CONNECTOR_PREFIX = 'connectors_ai_';
    const CONNECTOR_SUFFIX = '_api_key';
    const MASTER_SWITCH_OPTION = 'wpai_features_enabled';
    const FEATURE_PREFIX = 'wpai_feature_';
    const FEATURE_SUFFIX = '_enabled';

    /**
     * Option values snapshotted on delete_option so deleted_option can decide
     * whether the removal represents a disconnect / disable.
     *
     * @var array<string, mixed>
     */
    protected $deletionCache = [];

    /**
     * @return void
     */
    public function register()
    {
        add_action('added_option', [$this, 'onOptionAdded'], 10, 2);
        add_action('updated_option', [$this, 'onOptionUpdated'], 10, 3);
        add_action('delete_option', [$this, 'onBeforeDeleteOption'], 10, 1);
        add_action('deleted_option', [$this, 'onOptionDeleted'], 10, 1);
    }

    public function onOptionAdded($optionName, $value)
    {
        $optionName = is_string($optionName) ? $optionName : '';

        if ($optionName === '') {
            return;
        }

        $connectorSlug = $this->connectorSlugFromOption($optionName);

        if ($connectorSlug !== '') {
            if ($this->isApiKeySet($value)) {
                $this->recordConnectorEvent(true, $optionName, $connectorSlug);
            }
            return;
        }

        if ($optionName === self::MASTER_SWITCH_OPTION) {
            if ($this->toBool($value)) {
                $this->recordMasterSwitchEvent(true);
            }
            return;
        }

        $featureSlug = $this->featureSlugFromOption($optionName);

        if ($featureSlug !== '' && $this->toBool($value)) {
            $this->recordFeatureEvent(true, $featureSlug);
        }
    }

    public function onOptionUpdated($optionName, $oldValue, $newValue)
    {
        $optionName = is_string($optionName) ? $optionName : '';

        if ($optionName === '') {
            return;
        }

        $connectorSlug = $this->connectorSlugFromOption($optionName);

        if ($connectorSlug !== '') {
            $oldSet = $this->isApiKeySet($oldValue);
            $newSet = $this->isApiKeySet($newValue);

            if ($oldSet !== $newSet) {
                $this->recordConnectorEvent($newSet, $optionName, $connectorSlug);
            }
            return;
        }

        if ($optionName === self::MASTER_SWITCH_OPTION) {
            $this->emitBooleanChange($oldValue, $newValue, function ($enabled) {
                $this->recordMasterSwitchEvent($enabled);
            });
            return;
        }

        $featureSlug = $this->featureSlugFromOption($optionName);

        if ($featureSlug !== '') {
            $this->emitBooleanChange($oldValue, $newValue, function ($enabled) use ($featureSlug) {
                $this->recordFeatureEvent($enabled, $featureSlug);
            });
        }
    }

    public function onBeforeDeleteOption($optionName)
    {
        if (!is_string($optionName) || $optionName === '') {
            return;
        }

        if (!$this->isTrackedOption($optionName)) {
            return;
        }

        $this->deletionCache[$optionName] = get_option($optionName);
    }

    public function onOptionDeleted($optionName)
    {
        if (!is_string($optionName) || !array_key_exists($optionName, $this->deletionCache)) {
            return;
        }

        $oldValue = $this->deletionCache[$optionName];
        unset($this->deletionCache[$optionName]);

        $connectorSlug = $this->connectorSlugFromOption($optionName);

        if ($connectorSlug !== '') {
            if ($this->isApiKeySet($oldValue)) {
                $this->recordConnectorEvent(false, $optionName, $connectorSlug);
            }
            return;
        }

        if ($optionName === self::MASTER_SWITCH_OPTION) {
            if ($this->toBool($oldValue)) {
                $this->recordMasterSwitchEvent(false);
            }
            return;
        }

        $featureSlug = $this->featureSlugFromOption($optionName);

        if ($featureSlug !== '' && $this->toBool($oldValue)) {
            $this->recordFeatureEvent(false, $featureSlug);
        }
    }

    protected function recordConnectorEvent($connected, $optionName, $connectorSlug)
    {
        $info = $this->connectorInfo($optionName, $connectorSlug);

        $this->recordEvent(
            $connected ? 'ai.connector.connected' : 'ai.connector.disconnected',
            'MEDIUM',
            [
                'connectorSlug' => $connectorSlug,
                'connectorName' => $info['name'],
                'connectorPlugin' => $info['plugin'],
            ]
        );
    }

    protected function recordMasterSwitchEvent($enabled)
    {
        $this->recordEvent(
            $enabled ? 'ai.master_switch.enabled' : 'ai.master_switch.disabled',
            'MEDIUM'
        );
    }

    protected function recordFeatureEvent($enabled, $featureSlug)
    {
        $this->recordEvent(
            $enabled ? 'ai.feature.enabled' : 'ai.feature.disabled',
            'MEDIUM',
            [
                'featureSlug' => $featureSlug,
                'featureName' => $this->humanize($featureSlug),
            ]
        );
    }

    /**
     * Runs $emit(bool) only when the truthiness of the value actually flips.
     *
     * @param mixed    $oldValue
     * @param mixed    $newValue
     * @param callable $emit
     *
     * @return void
     */
    protected function emitBooleanChange($oldValue, $newValue, callable $emit)
    {
        $old = $this->toBool($oldValue);
        $new = $this->toBool($newValue);

        if ($old !== $new) {
            $emit($new);
        }
    }

    protected function isTrackedOption($optionName)
    {
        return $this->connectorSlugFromOption($optionName) !== ''
            || $optionName === self::MASTER_SWITCH_OPTION
            || $this->featureSlugFromOption($optionName) !== '';
    }

    /**
     * Extracts the connector slug from a connectors_ai_<slug>_api_key option.
     *
     * @param string $optionName
     *
     * @return string Slug, or empty string when the option is not a connector.
     */
    protected function connectorSlugFromOption($optionName)
    {
        return $this->extractSlug($optionName, self::CONNECTOR_PREFIX, self::CONNECTOR_SUFFIX);
    }

    /**
     * Extracts the feature slug from a wpai_feature_<slug>_enabled option.
     *
     * @param string $optionName
     *
     * @return string Slug, or empty string when the option is not a feature toggle.
     */
    protected function featureSlugFromOption($optionName)
    {
        return $this->extractSlug($optionName, self::FEATURE_PREFIX, self::FEATURE_SUFFIX);
    }

    protected function extractSlug($optionName, $prefix, $suffix)
    {
        if (!is_string($optionName)) {
            return '';
        }

        if (strpos($optionName, $prefix) !== 0) {
            return '';
        }

        if (substr($optionName, -strlen($suffix)) !== $suffix) {
            return '';
        }

        $slug = substr($optionName, strlen($prefix), -strlen($suffix));

        return is_string($slug) ? $slug : '';
    }

    /**
     * Resolves a human connector name and provider plugin from the WordPress
     * connector registry when it is available (WP 7.0+), falling back to a
     * humanized slug otherwise.
     *
     * @param string $optionName
     * @param string $connectorSlug
     *
     * @return array{name: string|null, plugin: string|null}
     */
    protected function connectorInfo($optionName, $connectorSlug)
    {
        $fallback = ['name' => $this->humanize($connectorSlug), 'plugin' => null];

        if (!function_exists('wp_get_connectors')) {
            return $fallback;
        }

        $connectors = wp_get_connectors();

        if (!is_array($connectors)) {
            return $fallback;
        }

        foreach ($connectors as $connector) {
            if (!is_array($connector)) {
                continue;
            }

            $authentication = isset($connector['authentication']) && is_array($connector['authentication'])
                ? $connector['authentication']
                : [];

            if (($authentication['setting_name'] ?? '') !== $optionName) {
                continue;
            }

            $name = isset($connector['name']) && is_string($connector['name']) && $connector['name'] !== ''
                ? $connector['name']
                : $fallback['name'];

            $plugin = isset($connector['plugin']['file']) && is_string($connector['plugin']['file'])
                ? $connector['plugin']['file']
                : null;

            return ['name' => $name, 'plugin' => $plugin];
        }

        return $fallback;
    }

    /**
     * @param mixed $value
     *
     * @return bool True when the API key option holds a non empty value.
     */
    protected function isApiKeySet($value)
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_scalar($value)) {
            return trim((string) $value) !== '';
        }

        return !empty($value);
    }

    /**
     * Coerces WordPress option values (including "0"/"1", "true"/"false") to bool.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function toBool($value)
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return !in_array($normalized, ['', '0', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    protected function humanize($slug)
    {
        if (!is_string($slug) || $slug === '') {
            return null;
        }

        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
