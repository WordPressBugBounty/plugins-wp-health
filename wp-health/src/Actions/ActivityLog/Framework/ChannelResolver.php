<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Auto detects the execution context (channel) of the current request.
 *
 * Possible values: cli, cron, ajax, rest, xmlrpc, admin, frontend.
 *
 * Why this matters: "the plugin deactivated itself" with channel = cron means
 * an auto update, not a human action. Surfacing the channel to the dashboard
 * eliminates the most common false alarm category for agency support.
 */
class ChannelResolver
{
    const CHANNEL_CLI = 'cli';
    const CHANNEL_CRON = 'cron';
    const CHANNEL_AJAX = 'ajax';
    const CHANNEL_REST = 'rest';
    const CHANNEL_XMLRPC = 'xmlrpc';
    const CHANNEL_ADMIN = 'admin';
    const CHANNEL_FRONTEND = 'frontend';

    /**
     * @var string|null
     */
    protected static $cached = null;

    /**
     * Resolves the current channel and caches it for the rest of the request.
     *
     * @return string
     */
    public static function resolve()
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        if (defined('WP_CLI') && WP_CLI) {
            self::$cached = self::CHANNEL_CLI;
        } elseif (defined('DOING_CRON') && DOING_CRON) {
            self::$cached = self::CHANNEL_CRON;
        } elseif (defined('DOING_AJAX') && DOING_AJAX) {
            self::$cached = self::CHANNEL_AJAX;
        } elseif (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            self::$cached = self::CHANNEL_XMLRPC;
        } elseif (defined('REST_REQUEST') && REST_REQUEST) {
            self::$cached = self::CHANNEL_REST;
        } elseif (function_exists('is_admin') && is_admin()) {
            self::$cached = self::CHANNEL_ADMIN;
        } else {
            self::$cached = self::CHANNEL_FRONTEND;
        }

        return self::$cached;
    }

    /**
     * Resets the cache. Test only helper.
     *
     * @return void
     */
    public static function reset()
    {
        self::$cached = null;
    }
}
