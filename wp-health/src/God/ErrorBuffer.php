<?php
namespace WPUmbrella\God;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\GodTransient;

/**
 * Bounded store for the PHP errors captured on the site.
 *
 * Written by ErrorHandler on shutdown, drained by the recurring Action
 * Scheduler job in src/Async/ActionSchedulerSendErrors.php. Entries are keyed
 * by the md5 of the error so a recurring error never takes two slots.
 *
 * The store is a single option written with autoload disabled. The volume is
 * small by construction (ErrorHandler mutes a distinct error for 12 hours) so
 * a dedicated table would buy nothing over a bounded array.
 *
 * This file is loaded from the mu-plugin handler, which only includes
 * GodTransient.php and ErrorHandler.php, so it must not depend on anything
 * else in the plugin.
 */
class ErrorBuffer
{
    const OPTION = 'wp_umbrella_errors_buffer';

    const MAX_ENTRIES = 100;

    const MAX_AGE_SECONDS = 604800;

    /**
     * Returns every buffered error, oldest first.
     *
     * @return array
     */
    public static function all()
    {
        self::importLegacyStorage();

        $buffer = get_option(self::OPTION, []);

        return is_array($buffer) ? $buffer : [];
    }

    /**
     * Adds an error unless the same one is already waiting to be sent.
     *
     * @param string $key
     * @param array  $entry
     *
     * @return void
     */
    public static function add($key, array $entry)
    {
        $buffer = self::all();

        if (array_key_exists($key, $buffer)) {
            return;
        }

        $buffer[$key] = $entry;

        self::save(self::enforceCaps($buffer));
    }

    /**
     * Drops the given keys. Called only once the API acknowledged them.
     *
     * @param array $keys
     *
     * @return void
     */
    public static function remove(array $keys)
    {
        if (empty($keys)) {
            return;
        }

        $buffer = self::all();

        foreach ($keys as $key) {
            unset($buffer[$key]);
        }

        self::save($buffer);
    }

    /**
     * Applies the caps outside of any write, so a buffer left behind by a
     * broken sync cannot grow stale forever.
     *
     * @return void
     */
    public static function prune()
    {
        $buffer = self::all();
        $pruned = self::enforceCaps($buffer);

        if (count($pruned) === count($buffer)) {
            return;
        }

        self::save($pruned);
    }

    /**
     * @return int
     */
    public static function count()
    {
        return count(self::all());
    }

    /**
     * @param array $buffer
     *
     * @return void
     */
    protected static function save(array $buffer)
    {
        if (empty($buffer)) {
            delete_option(self::OPTION);

            return;
        }

        update_option(self::OPTION, $buffer, false);
    }

    /**
     * Drops the entries older than the age cap, then the oldest entries above
     * the count cap.
     *
     * @param array $buffer
     *
     * @return array
     */
    protected static function enforceCaps(array $buffer)
    {
        $cutoff = time() - self::MAX_AGE_SECONDS;

        foreach ($buffer as $key => $entry) {
            if (!is_array($entry) || self::capturedAt($entry) < $cutoff) {
                unset($buffer[$key]);
            }
        }

        $overflow = count($buffer) - self::MAX_ENTRIES;

        if ($overflow > 0) {
            $buffer = array_slice($buffer, $overflow, null, true);
        }

        return $buffer;
    }

    /**
     * @param array $entry
     *
     * @return int
     */
    protected static function capturedAt(array $entry)
    {
        if (!isset($entry['date_error']) || !is_string($entry['date_error'])) {
            return time();
        }

        $timestamp = strtotime($entry['date_error']);

        return false === $timestamp ? time() : $timestamp;
    }

    /**
     * Adopts whatever the previous plugin version left behind, in the
     * transient it used to write and in the autoloaded option the admin side
     * used to move it to. Both are removed once adopted, which is also what
     * takes the autoloaded blob out of every page load.
     *
     * @return void
     */
    protected static function importLegacyStorage()
    {
        $legacy = [];

        $transient = get_transient(GodTransient::ERRORS_SAVE);
        if (is_array($transient)) {
            $legacy = $transient;
            delete_transient(GodTransient::ERRORS_SAVE);
        }

        $option = get_option(GodTransient::ERRORS_SAVE);
        if (is_array($option)) {
            $legacy = array_merge($option, $legacy);
            delete_option(GodTransient::ERRORS_SAVE);
        }

        if (empty($legacy)) {
            return;
        }

        $buffer = get_option(self::OPTION, []);
        if (!is_array($buffer)) {
            $buffer = [];
        }

        $adoptedAt = gmdate('Y-m-d\TH:i:s\Z');

        foreach ($legacy as $key => $entry) {
            if (!is_array($entry) || array_key_exists($key, $buffer)) {
                continue;
            }

            if (!isset($entry['date_error'])) {
                $entry['date_error'] = $adoptedAt;
            }

            $buffer[$key] = $entry;
        }

        self::save(self::enforceCaps($buffer));
    }
}
