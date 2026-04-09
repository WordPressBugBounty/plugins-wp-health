<?php
namespace WPUmbrella\Services;

/**
 * Tracks the state of plugin/theme updates via non-autoloaded wp_options.
 *
 * The worker reads this state before deciding whether to attempt a rollback,
 * preventing race conditions between the WP core shutdown handler and the
 * worker's own rollback logic.
 */
class UpdateStateManager
{
    const NAME_SERVICE = 'UpdateStateManager';

    const STATUS_UPDATING = 'updating';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_WP_CORE_ROLLBACK_SCHEDULED = 'wp_core_rollback_scheduled';
    const STATUS_WP_CORE_ROLLBACK_DONE = 'wp_core_rollback_done';
    const STATUS_WP_CORE_ROLLBACK_FAILED = 'wp_core_rollback_failed';

    /**
     * Statuses owned by WP core's rollback mechanism.
     * Once in one of these states, only another WP core rollback status can override it.
     * This prevents bulkUpdate's error handling from overwriting a scheduled rollback with 'failed'.
     */
    const WP_CORE_ROLLBACK_STATUSES = [
        self::STATUS_WP_CORE_ROLLBACK_SCHEDULED,
        self::STATUS_WP_CORE_ROLLBACK_DONE,
        self::STATUS_WP_CORE_ROLLBACK_FAILED,
    ];

    /**
     * Maximum seconds a 'wp_core_rollback_scheduled' state can remain before being
     * considered stuck (shutdown hook never fired — OOM kill, PHP-FPM timeout, etc.).
     */
    const WP_CORE_ROLLBACK_TIMEOUT = 60;

    /**
     * Build the option key for a given slug and type.
     *
     * @param string $slug Plugin directory name or theme slug
     * @param string $type 'plugin' or 'theme'
     */
    protected function getOptionKey($slug, $type = 'plugin')
    {
        return 'wp_umbrella_update_state_' . $type . '_' . $slug;
    }

    /**
     * Set the update state for a plugin or theme.
     *
     * @param string $slug   Plugin directory name (e.g. "elementor") or theme slug
     * @param string $status One of the STATUS_* constants
     * @param array  $extra  Additional data to merge (old_version, plugin file, etc.)
     * @param string $type   'plugin' or 'theme'
     */
    public function setState($slug, $status, $extra = [], $type = 'plugin')
    {
        $key = $this->getOptionKey($slug, $type);

        $state = array_merge([
            'status' => $status,
            'started_at' => time(),
            'slug' => $slug,
            'type' => $type,
        ], $extra);

        // Non-autoloaded (false) → not loaded on every page load, no object cache bypass needed
        update_option($key, $state, false);

        wp_umbrella_debug_log("UpdateStateManager: {$type} '{$slug}' → {$status}");
    }

    /**
     * Update only the status field (preserves started_at and other data).
     *
     * @param string $slug
     * @param string $status
     * @param array  $extra  Additional data to merge
     * @param string $type
     */
    public function updateStatus($slug, $status, $extra = [], $type = 'plugin')
    {
        $key = $this->getOptionKey($slug, $type);

        $current = get_option($key, false);

        if (!is_array($current)) {
            // No existing state — create fresh
            $this->setState($slug, $status, $extra, $type);
            return;
        }

        // Guard: WP core rollback states can only be overridden by other WP core rollback states.
        // This prevents bulkUpdate's error handling from overwriting 'wp_core_rollback_scheduled'
        // with 'failed', which would cause the worker to launch a duplicate rollback.
        $currentStatus = isset($current['status']) ? $current['status'] : null;
        $isWpCoreOwned = in_array($currentStatus, self::WP_CORE_ROLLBACK_STATUSES, true);
        $isWpCoreTransition = in_array($status, self::WP_CORE_ROLLBACK_STATUSES, true);

        if ($isWpCoreOwned && !$isWpCoreTransition) {
            wp_umbrella_debug_log("UpdateStateManager: blocked transition {$type} '{$slug}' from '{$currentStatus}' to '{$status}' (WP core owns rollback)");
            return;
        }

        $current['status'] = $status;
        $current = array_merge($current, $extra);

        update_option($key, $current, false);

        wp_umbrella_debug_log("UpdateStateManager: {$type} '{$slug}' status → {$status}");
    }

    /**
     * Get the current update state for a plugin or theme.
     *
     * @param string $slug
     * @param string $type
     * @return array|null
     */
    public function getState($slug, $type = 'plugin')
    {
        $key = $this->getOptionKey($slug, $type);

        $state = get_option($key, false);

        if (!is_array($state)) {
            return null;
        }

        // Add computed elapsed time
        $state['elapsed'] = isset($state['started_at']) ? time() - $state['started_at'] : null;

        // Auto-resolve stuck 'wp_core_rollback_scheduled': if the shutdown hook never fired
        // (OOM kill, PHP-FPM timeout, etc.), the state would remain scheduled forever.
        // Shutdown hooks execute in < 1 second, so 60s is very generous.
        if (
            isset($state['status']) &&
            $state['status'] === self::STATUS_WP_CORE_ROLLBACK_SCHEDULED &&
            $state['elapsed'] !== null &&
            $state['elapsed'] > self::WP_CORE_ROLLBACK_TIMEOUT
        ) {
            wp_umbrella_debug_log("UpdateStateManager: auto-resolving stuck wp_core_rollback_scheduled for '{$slug}' (elapsed: {$state['elapsed']}s)");

            $state['status'] = self::STATUS_WP_CORE_ROLLBACK_FAILED;
            $state['auto_resolved'] = true;
            $state['auto_resolved_reason'] = 'shutdown_hook_never_fired';

            // Persist so we don't recompute every time
            update_option($key, $state, false);
        }

        return $state;
    }

    /**
     * Check whether any plugin or theme update is still in progress.
     * Used by MaintenanceMode to avoid removing .maintenance while another update is running.
     *
     * @return bool
     */
    public function hasActiveUpdates()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wp_umbrella_update_state_%'
            )
        );

        if (empty($results)) {
            return false;
        }

        foreach ($results as $row) {
            $state = maybe_unserialize($row->option_value);

            if (!is_array($state) || !isset($state['status'])) {
                continue;
            }

            if ($state['status'] === self::STATUS_UPDATING || $state['status'] === self::STATUS_WP_CORE_ROLLBACK_SCHEDULED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete the update state (cleanup).
     *
     * @param string $slug
     * @param string $type
     */
    public function deleteState($slug, $type = 'plugin')
    {
        $key = $this->getOptionKey($slug, $type);

        delete_option($key);

        wp_umbrella_debug_log("UpdateStateManager: {$type} '{$slug}' state deleted");
    }

    /**
     * Clean up stale update states older than the given TTL.
     * Called opportunistically to prevent orphaned options.
     *
     * @param int $ttlSeconds Maximum age before an option is considered stale (default 10 minutes,
     *                        accounts for post-update screenshot capture)
     */
    public function cleanupStaleStates($ttlSeconds = 600)
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wp_umbrella_update_state_%'
            )
        );

        if (empty($results)) {
            return;
        }

        $now = time();

        foreach ($results as $row) {
            $state = maybe_unserialize($row->option_value);

            if (!is_array($state) || !isset($state['started_at'])) {
                delete_option($row->option_name);
                continue;
            }

            if (($now - $state['started_at']) > $ttlSeconds) {
                wp_umbrella_debug_log("UpdateStateManager: cleaning stale state '{$row->option_name}' (age: " . ($now - $state['started_at']) . 's)');
                delete_option($row->option_name);
            }
        }
    }
}
