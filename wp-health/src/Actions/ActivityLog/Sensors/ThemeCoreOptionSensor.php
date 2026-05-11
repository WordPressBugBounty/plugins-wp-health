<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures theme, core and security relevant option events.
 *
 * Event keys emitted:
 * - theme.activated    (HIGH)
 * - theme.installed    (MEDIUM)
 * - theme.updated      (MEDIUM)
 * - theme.deleted      (HIGH)
 * - core.updated       (HIGH)
 * - option.updated     (HIGH for security relevant keys, LOW otherwise)
 *
 * Implementation notes:
 * - upgrader_process_complete is multi purpose. The dispatch checks
 *   $hookExtra['type'] and only acts on 'theme', 'core' and 'translation'.
 *   Plugin updates go through PluginSensor.
 * - WordPress does not expose a "theme installed" hook. We infer it from
 *   upgrader_process_complete with action=install.
 * - updated_option fires for every option write, including hundreds of WP
 *   internals. We only emit for a curated whitelist plus a filter for
 *   site owners who want to extend it.
 */
class ThemeCoreOptionSensor extends AbstractSensor
{
    /**
     * Default whitelist of options worth tracking. The map controls the
     * severity emitted, with a focus on security relevant changes:
     * - CRITICAL for events that point to active compromise (capability map)
     * - HIGH for security primitives (admin URL, registration, anti-spam)
     * - MEDIUM for moderation-side effects and visible content layout
     * - LOW for cosmetic/identity changes
     *
     * @var array<string, string>
     */
    protected static $defaultTrackedOptions = [
        // Capability map — direct manipulation is the smoking gun of a shell
        'wp_user_roles' => 'CRITICAL',

        // Admin URL / account hijack
        'siteurl' => 'HIGH',
        'home' => 'HIGH',
        'admin_email' => 'HIGH',

        // Account creation / privilege escalation
        'default_role' => 'HIGH',
        'users_can_register' => 'HIGH',

        // Anti-spam / comment moderation primitives
        'moderation_keys' => 'HIGH',
        'disallowed_keys' => 'HIGH',
        'require_name_email' => 'HIGH',
        'comment_moderation' => 'HIGH',
        'comment_previously_approved' => 'MEDIUM',
        'comment_max_links' => 'MEDIUM',
        'comment_registration' => 'MEDIUM',
        'default_comment_status' => 'LOW',

        // Visible content and routing
        'blog_public' => 'MEDIUM',
        'permalink_structure' => 'MEDIUM',
        'show_on_front' => 'MEDIUM',
        'page_on_front' => 'MEDIUM',
        'page_for_posts' => 'MEDIUM',

        // Identity / locale (low signal)
        'blogname' => 'LOW',
        'blogdescription' => 'LOW',
        'WPLANG' => 'LOW',
        'timezone_string' => 'LOW',
    ];

    /**
     * Per request cache for the resolved option whitelist.
     *
     * @var array<string, string>|null
     */
    protected static $trackedOptionsCache = null;

    /**
     * Snapshot captured on delete_theme so deleted_theme can emit a
     * meaningful event after the theme directory is gone.
     *
     * @var array<string, array{name: string, version: string}>
     */
    protected $themeDeletionCache = [];

    /**
     * @return void
     */
    public function register()
    {
        add_action('switch_theme', [$this, 'onThemeSwitched'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'onUpgraderComplete'], 10, 2);
        add_action('delete_theme', [$this, 'onBeforeDeleteTheme'], 10, 1);
        add_action('deleted_theme', [$this, 'onThemeDeleted'], 10, 2);
        add_action('_core_updated_successfully', [$this, 'onCoreUpdated'], 10, 1);
        add_action('updated_option', [$this, 'onOptionUpdated'], 10, 3);
    }

    public function onThemeSwitched($newName, $newTheme = null, $oldTheme = null)
    {
        $newInfo = $this->themeInfoFromObject($newTheme);
        $oldInfo = $this->themeInfoFromObject($oldTheme);

        $this->recordEvent('theme.activated', 'HIGH', [
            'themeName' => is_string($newName) ? $newName : ($newInfo !== null ? $newInfo['name'] : null),
            'themeVersion' => $newInfo !== null ? $newInfo['version'] : null,
            'themeStylesheet' => $newInfo !== null ? $newInfo['stylesheet'] : null,
            'previousThemeName' => $oldInfo !== null ? $oldInfo['name'] : null,
            'previousThemeStylesheet' => $oldInfo !== null ? $oldInfo['stylesheet'] : null,
        ]);
    }

    public function onUpgraderComplete($upgrader, $hookExtra)
    {
        if (!is_array($hookExtra) || !isset($hookExtra['type'])) {
            return;
        }

        $type = (string) $hookExtra['type'];
        $action = isset($hookExtra['action']) ? (string) $hookExtra['action'] : '';

        if ($type === 'theme') {
            $this->dispatchThemeUpgrader($action, $hookExtra);
            return;
        }

        if ($type === 'core') {
            // core.updated is the canonical event; _core_updated_successfully
            // is the more specific WP hook used for core upgrades. We emit
            // here too in case _core_updated_successfully did not fire (some
            // hosts intercept the install path).
            $this->recordEvent('core.updated', 'HIGH', [
                'wpVersion' => isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : null,
                'action' => $action,
            ]);
            return;
        }

        if ($type === 'translation') {
            // Translations are noisy and low value. Skip for v1.
            return;
        }
    }

    public function onBeforeDeleteTheme($stylesheet)
    {
        if (!is_string($stylesheet) || $stylesheet === '') {
            return;
        }

        $info = $this->themeInfoByStylesheet($stylesheet);

        if ($info !== null) {
            $this->themeDeletionCache[$stylesheet] = [
                'name' => $info['name'],
                'version' => $info['version'],
            ];
        }
    }

    public function onThemeDeleted($stylesheet, $deleted = null)
    {
        if ($deleted === false || !is_string($stylesheet) || $stylesheet === '') {
            return;
        }

        $cached = isset($this->themeDeletionCache[$stylesheet]) ? $this->themeDeletionCache[$stylesheet] : null;
        unset($this->themeDeletionCache[$stylesheet]);

        $this->recordEvent('theme.deleted', 'HIGH', [
            'themeName' => $cached !== null ? $cached['name'] : null,
            'themeVersion' => $cached !== null ? $cached['version'] : null,
            'themeStylesheet' => $stylesheet,
        ]);
    }

    public function onCoreUpdated($wpVersion)
    {
        $this->recordEvent('core.updated', 'HIGH', [
            'wpVersion' => is_string($wpVersion) ? $wpVersion : null,
        ]);
    }

    public function onOptionUpdated($optionName, $oldValue, $newValue)
    {
        $optionName = is_string($optionName) ? $optionName : '';

        if ($optionName === '') {
            return;
        }

        $tracked = self::getTrackedOptions();

        if (!array_key_exists($optionName, $tracked)) {
            return;
        }

        $severity = $tracked[$optionName];

        $this->recordEvent('option.updated', $severity, [
            'optionName' => $optionName,
            'oldValue' => $this->stringifyValue($oldValue),
            'newValue' => $this->stringifyValue($newValue),
        ]);
    }

    /**
     * Returns the resolved option whitelist with severity. Filterable via
     * wp_umbrella_activity_log_tracked_options ([optionName => severity]).
     *
     * @return array<string, string>
     */
    protected static function getTrackedOptions()
    {
        if (self::$trackedOptionsCache !== null) {
            return self::$trackedOptionsCache;
        }

        $tracked = self::$defaultTrackedOptions;

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('wp_umbrella_activity_log_tracked_options', $tracked);

            if (is_array($filtered)) {
                $tracked = $filtered;
            }
        }

        self::$trackedOptionsCache = $tracked;

        return self::$trackedOptionsCache;
    }

    /**
     * Resets the tracked option cache. Test only helper.
     *
     * @return void
     */
    public static function resetCache()
    {
        self::$trackedOptionsCache = null;
    }

    protected function dispatchThemeUpgrader($action, array $hookExtra)
    {
        $themes = [];

        if (isset($hookExtra['themes']) && is_array($hookExtra['themes'])) {
            $themes = $hookExtra['themes'];
        } elseif (isset($hookExtra['theme']) && is_string($hookExtra['theme'])) {
            $themes = [$hookExtra['theme']];
        }

        $eventKey = $action === 'install' ? 'theme.installed' : 'theme.updated';

        foreach ($themes as $stylesheet) {
            if (!is_string($stylesheet) || $stylesheet === '') {
                continue;
            }

            $info = $this->themeInfoByStylesheet($stylesheet);

            $this->recordEvent($eventKey, 'MEDIUM', [
                'themeStylesheet' => $stylesheet,
                'themeName' => $info !== null ? $info['name'] : null,
                'themeVersion' => $info !== null ? $info['version'] : null,
            ]);
        }
    }

    /**
     * @param string $stylesheet
     *
     * @return array{name: string, version: string, stylesheet: string}|null
     */
    protected function themeInfoByStylesheet($stylesheet)
    {
        if (!function_exists('wp_get_theme')) {
            return null;
        }

        $theme = wp_get_theme($stylesheet);

        if (!is_object($theme) || (method_exists($theme, 'exists') && !$theme->exists())) {
            return null;
        }

        return [
            'name' => method_exists($theme, 'get') ? (string) $theme->get('Name') : '',
            'version' => method_exists($theme, 'get') ? (string) $theme->get('Version') : '',
            'stylesheet' => $stylesheet,
        ];
    }

    /**
     * @param mixed $theme
     *
     * @return array{name: string, version: string, stylesheet: string}|null
     */
    protected function themeInfoFromObject($theme)
    {
        if (!is_object($theme)) {
            return null;
        }

        $name = method_exists($theme, 'get') ? (string) $theme->get('Name') : '';
        $version = method_exists($theme, 'get') ? (string) $theme->get('Version') : '';
        $stylesheet = method_exists($theme, 'get_stylesheet') ? (string) $theme->get_stylesheet() : '';

        return [
            'name' => $name,
            'version' => $version,
            'stylesheet' => $stylesheet,
        ];
    }

    /**
     * Coerces an option value into a short string for inclusion in the
     * payload. Non scalar values are JSON encoded; everything is truncated
     * to a safe length to avoid bloating the buffer.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function stringifyValue($value)
    {
        if (is_scalar($value) || $value === null) {
            $string = $value === null ? '' : (string) $value;
        } else {
            $encoded = function_exists('wp_json_encode') ? wp_json_encode($value) : json_encode($value);
            $string = $encoded === false ? '' : (string) $encoded;
        }

        if (strlen($string) > 500) {
            return substr($string, 0, 500) . '...';
        }

        return $string;
    }
}
