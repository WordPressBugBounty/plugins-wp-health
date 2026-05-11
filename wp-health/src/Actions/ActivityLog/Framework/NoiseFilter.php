<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Activity log noise filter.
 *
 * Provides hardcoded exclusion lists, configurable via WordPress filters,
 * to prevent the activity log from being polluted by WordPress internal
 * churn (revisions, transients, edit locks, ...).
 *
 * The wp_umbrella_activity_log_noise_filter filter allows third party code
 * to short circuit the decision for any event before insertion in the buffer.
 */
class NoiseFilter
{
    /**
     * Default post types that must never be logged.
     *
     * @var array
     */
    protected static $defaultExcludedPostTypes = [
        'revision',
        'auto-draft',
        'oembed_cache',
    ];

    /**
     * Default option name prefixes that must never be logged.
     *
     * @var array
     */
    protected static $defaultExcludedOptionPrefixes = [
        '_transient_',
        '_site_transient_',
        'cron',
        'recently_activated',
        '_edit_lock',
    ];

    /**
     * Default postmeta key prefixes that must never be logged.
     *
     * @var array
     */
    protected static $defaultExcludedMetaPrefixes = [
        '_edit_lock',
        '_edit_last',
    ];

    /**
     * Per request cache for resolved exclusion lists.
     *
     * @var array
     */
    protected static $cache = [];

    /**
     * Decides whether an event should be skipped entirely.
     *
     * @param string $eventKey
     * @param array  $context
     *
     * @return bool
     */
    public static function shouldSkip($eventKey, array $context = [])
    {
        if (self::matchesPostType($eventKey, $context)) {
            return true;
        }

        if (self::matchesOptionKey($eventKey, $context)) {
            return true;
        }

        if (self::matchesMetaKey($eventKey, $context)) {
            return true;
        }

        /**
         * Allows third party code to extend or override the noise filter.
         *
         * Return true to skip the event, false to keep it.
         *
         * @param bool   $shouldSkip
         * @param string $eventKey
         * @param array  $context
         */
        return (bool) apply_filters('wp_umbrella_activity_log_noise_filter', false, $eventKey, $context);
    }

    /**
     * @param string $eventKey
     * @param array  $context
     *
     * @return bool
     */
    protected static function matchesPostType($eventKey, array $context)
    {
        if (strpos($eventKey, 'post.') !== 0) {
            return false;
        }

        if (!isset($context['postType']) || !is_string($context['postType'])) {
            return false;
        }

        $excluded = self::getExcludedPostTypes();

        return in_array($context['postType'], $excluded, true);
    }

    /**
     * @param string $eventKey
     * @param array  $context
     *
     * @return bool
     */
    protected static function matchesOptionKey($eventKey, array $context)
    {
        if (strpos($eventKey, 'option.') !== 0) {
            return false;
        }

        if (!isset($context['optionName']) || !is_string($context['optionName'])) {
            return false;
        }

        return self::startsWithAny($context['optionName'], self::getExcludedOptionPrefixes());
    }

    /**
     * @param string $eventKey
     * @param array  $context
     *
     * @return bool
     */
    protected static function matchesMetaKey($eventKey, array $context)
    {
        if (strpos($eventKey, 'postmeta.') !== 0) {
            return false;
        }

        if (!isset($context['metaKey']) || !is_string($context['metaKey'])) {
            return false;
        }

        return self::startsWithAny($context['metaKey'], self::getExcludedMetaPrefixes());
    }

    /**
     * @param string $value
     * @param array  $prefixes
     *
     * @return bool
     */
    protected static function startsWithAny($value, array $prefixes)
    {
        foreach ($prefixes as $prefix) {
            if ($prefix === '') {
                continue;
            }

            if (strpos($value, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    protected static function getExcludedPostTypes()
    {
        if (!isset(self::$cache['post_types'])) {
            self::$cache['post_types'] = (array) apply_filters(
                'wp_umbrella_activity_log_excluded_post_types',
                self::$defaultExcludedPostTypes
            );
        }

        return self::$cache['post_types'];
    }

    /**
     * @return array
     */
    protected static function getExcludedOptionPrefixes()
    {
        if (!isset(self::$cache['option_prefixes'])) {
            self::$cache['option_prefixes'] = (array) apply_filters(
                'wp_umbrella_activity_log_excluded_option_prefixes',
                self::$defaultExcludedOptionPrefixes
            );
        }

        return self::$cache['option_prefixes'];
    }

    /**
     * @return array
     */
    protected static function getExcludedMetaPrefixes()
    {
        if (!isset(self::$cache['meta_prefixes'])) {
            self::$cache['meta_prefixes'] = (array) apply_filters(
                'wp_umbrella_activity_log_excluded_meta_prefixes',
                self::$defaultExcludedMetaPrefixes
            );
        }

        return self::$cache['meta_prefixes'];
    }

    /**
     * Resets the per request cache. Test only helper.
     *
     * @return void
     */
    public static function reset()
    {
        self::$cache = [];
    }
}
