<?php
namespace WPUmbrella\Services\Scheduler;

class SchedulerLock
{
    public function isLocked(string $key): bool
    {
        $untilLocked = get_option($key);

        if (!$untilLocked) {
            return false;
        }

        return $untilLocked > time();
    }

    public function lock(string $key, int $expiration = 60): void
    {
        update_option($key, time() + $expiration, $expiration);
    }
}
