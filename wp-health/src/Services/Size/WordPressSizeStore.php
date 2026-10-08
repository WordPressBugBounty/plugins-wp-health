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

    public function refresh()
    {
        if ($this->isRunning()) {
            return $this->getState();
        }

        if (!$this->claimRun()) {
            return $this->getState();
        }

        $record = $this->getRecord();

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
