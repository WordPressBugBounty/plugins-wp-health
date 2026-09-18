<?php
namespace WPUmbrella\Services\Size;

if (!defined('ABSPATH')) {
    exit;
}

class WordPressSizeStore
{
    const NAME_SERVICE = 'WordPressSizeStore';

    const OPTION_KEY = 'wp_umbrella_wordpress_sizes';

    const LOCK_OPTION_KEY = 'wp_umbrella_wordpress_sizes_lock';

    const STALE_AFTER_SECONDS = 604800;

    const RETRY_AFTER_SECONDS = 86400;

    const RUN_WINDOW_SECONDS = 900;

    /**
     * Measures the install and stores the result. Walking the whole tree can
     * take tens of seconds on a large site, so this is meant to run from the
     * scheduled action rather than from a request someone is waiting on.
     */
    public function refresh()
    {
        if ($this->isRunning()) {
            return $this->getState();
        }

        if (!$this->claimRun()) {
            return $this->getState();
        }

        $record = $this->getRecord();

        // Stamped before the walk, so an install too large to finish measuring
        // itself still leaves a trace of having tried.
        $record['attempted_at'] = gmdate('c');
        update_option(self::OPTION_KEY, $record, false);

        $sizes = wp_umbrella_get_service('WordPressDataProvider')->getSizes();

        if (!$this->isComplete($sizes)) {
            $this->releaseRun();

            return null;
        }

        $record['sizes'] = $sizes;
        $record['computed_at'] = gmdate('c');

        update_option(self::OPTION_KEY, $record, false);

        $this->releaseRun();

        return $record;
    }

    /**
     * add_option() does not overwrite, so only one caller takes the claim. A
     * claim older than the run window belongs to a walk that never ended.
     *
     * @return bool
     */
    protected function claimRun()
    {
        $claimedAt = get_option(self::LOCK_OPTION_KEY, false);

        if ($claimedAt !== false) {
            if (is_numeric($claimedAt) && (time() - (int) $claimedAt) < self::RUN_WINDOW_SECONDS) {
                return false;
            }

            delete_option(self::LOCK_OPTION_KEY);
        }

        return (bool) add_option(self::LOCK_OPTION_KEY, time(), '', false);
    }

    protected function releaseRun()
    {
        delete_option(self::LOCK_OPTION_KEY);
    }

    public function getState()
    {
        $record = $this->getRecord();

        if (!isset($record['sizes']) || !is_array($record['sizes'])) {
            return null;
        }

        return $record;
    }

    public function isStale($state)
    {
        if (!is_array($state) || empty($state['computed_at'])) {
            return true;
        }

        $computedAt = strtotime($state['computed_at']);

        if (!$computedAt) {
            return true;
        }

        return (time() - $computedAt) > self::STALE_AFTER_SECONDS;
    }

    /**
     * A measurement is in flight when an attempt was stamped recently and no
     * result has been stored since. The marker lives in the option rather than
     * in a transient because the two contexts that can start a walk do not
     * share a transient backend: a persistent object cache keeps transients out
     * of the database entirely, while our own requests run with the external
     * object cache turned off and only ever see the database.
     */
    public function isRunning()
    {
        $record = $this->getRecord();

        if (empty($record['attempted_at'])) {
            return false;
        }

        $attemptedAt = strtotime($record['attempted_at']);

        if (!$attemptedAt || (time() - $attemptedAt) >= self::RUN_WINDOW_SECONDS) {
            return false;
        }

        if (empty($record['computed_at'])) {
            return true;
        }

        $computedAt = strtotime($record['computed_at']);

        return !$computedAt || $computedAt < $attemptedAt;
    }

    /**
     * Whether a measurement is worth queueing. An install that never manages to
     * finish stores no result, so the age of the last attempt decides, not the
     * age of the last result.
     */
    public function shouldScan()
    {
        $record = $this->getRecord();

        if (!$this->isStale($record)) {
            return false;
        }

        if ($this->isRunning()) {
            return false;
        }

        if (empty($record['attempted_at'])) {
            return true;
        }

        $attemptedAt = strtotime($record['attempted_at']);

        if (!$attemptedAt) {
            return true;
        }

        return (time() - $attemptedAt) > self::RETRY_AFTER_SECONDS;
    }

    /**
     * WP_Debug_Data::get_sizes() stops itself when it runs out of execution
     * budget, or when a directory cannot be read, and still answers an array:
     * the entries it could not measure carry no `raw`, and so does the total.
     * Storing that would replace a good measurement with a partial one and
     * stamp it fresh for a week, on exactly the installs too large to measure.
     */
    protected function isComplete($sizes)
    {
        return is_array($sizes)
            && isset($sizes['total_size']['raw'])
            && is_numeric($sizes['total_size']['raw']);
    }

    protected function getRecord()
    {
        $record = get_option(self::OPTION_KEY);

        return is_array($record) ? $record : [];
    }
}
