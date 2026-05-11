<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Debug logger for the activity log pipeline.
 *
 * Writes to the PHP error log so the messages flow into wp-content/debug.log
 * when WP_DEBUG_LOG is on. Output is gated behind WP_DEBUG to avoid
 * polluting production logs.
 *
 * Toggle:
 * - WP_DEBUG must be true (WordPress global debug switch).
 * - Define WP_UMBRELLA_ACTIVITY_LOG_DEBUG = false in wp-config.php to opt out.
 * - Filter wp_umbrella_activity_log_debug for runtime control.
 */
class ActivityLogLogger
{
    const PREFIX = '[wp-umbrella][activity-log]';

    /**
     * @var bool|null
     */
    protected static $cachedEnabled = null;

    public static function debug($message, array $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }

        self::write('DEBUG', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }

        self::write('INFO', $message, $context);
    }

    public static function warning($message, array $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }

        self::write('WARNING', $message, $context);
    }

    public static function isEnabled()
    {
        if (self::$cachedEnabled !== null) {
            return self::$cachedEnabled;
        }

        $enabled = defined('WP_DEBUG') && WP_DEBUG === true;

        if ($enabled && defined('WP_UMBRELLA_ACTIVITY_LOG_DEBUG')) {
            $enabled = (bool) WP_UMBRELLA_ACTIVITY_LOG_DEBUG;
        }

        if (function_exists('apply_filters')) {
            $enabled = (bool) apply_filters('wp_umbrella_activity_log_debug', $enabled);
        }

        self::$cachedEnabled = $enabled;

        return self::$cachedEnabled;
    }

    /**
     * Resets the cached enabled flag. Test only helper, also useful when a
     * filter callback is registered after the first call.
     *
     * @return void
     */
    public static function reset()
    {
        self::$cachedEnabled = null;
    }

    protected static function write($level, $message, array $context)
    {
        $line = self::PREFIX . '[' . $level . '] ' . (string) $message;

        if (!empty($context)) {
            $encoded = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);

            if ($encoded !== false) {
                $line .= ' ' . $encoded;
            }
        }

        error_log($line);
    }
}
