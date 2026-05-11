<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Self referential logging protection.
 *
 * WP Umbrella itself acts on client sites (backups, updates, scans) via the REST API
 * and internal hooks. Without protection, sensors would capture WPU's own actions,
 * polluting the activity log with internal operations.
 *
 * Every WPU entry point should call WPUmbrellaContext::setActing(true) before dispatching.
 * AbstractSensor::recordEvent() checks isActing() and returns early if true.
 *
 * The flag dies with the PHP request, no reset needed between requests.
 */
class WPUmbrellaContext
{
    /**
     * @var bool
     */
    protected static $isActing = false;

    /**
     * @var bool
     */
    protected static $isInUserDeletion = false;

    /**
     * Returns true when WP Umbrella is currently performing an action on the site.
     *
     * @return bool
     */
    public static function isActing()
    {
        return self::$isActing;
    }

    /**
     * Marks (or unmarks) the current request as a WP Umbrella action.
     *
     * @param bool $value
     *
     * @return void
     */
    public static function setActing($value)
    {
        self::$isActing = (bool) $value;
    }

    /**
     * True while WordPress is cascading a delete_user across the user's
     * posts and attachments. Used by content sensors to skip the
     * cascade events so a single user.deleted reports the bulk action
     * once, with counters, rather than 60+ post.deleted / media.deleted
     * lines per real human action.
     *
     * @return bool
     */
    public static function isInUserDeletion()
    {
        return self::$isInUserDeletion;
    }

    public static function setInUserDeletion($value)
    {
        self::$isInUserDeletion = (bool) $value;
    }
}
