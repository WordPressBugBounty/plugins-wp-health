<?php

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Actions\ActivityLog\Framework\ActivityLogLogger;
use WPUmbrella\Actions\ActivityLog\Framework\EventBuffer;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;

/**
 * Action Scheduler handler for the activity log sync.
 *
 * Mirrors the pattern of ActionSchedulerSendErrors.php (procedural file
 * loaded by Kernel::handleHooksPlugin() on plugins_loaded).
 *
 * Drains the local event buffer, POSTs the batch to the worker, deletes
 * acknowledged rows. Auth uses the standard plugin headers (Bearer api key
 * + X-Project + X-Project-Id + X-Secret-Token), same as the error log.
 */

define('WP_UMBRELLA_ACTIVITY_LOG_BATCH_SIZE', 300);
define('WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_ROWS', 10000);
define('WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_AGE_DAYS', 7);
define('WP_UMBRELLA_ACTIVITY_LOG_MAX_ITERATIONS_PER_RUN', 5);
define('WP_UMBRELLA_ACTIVITY_LOG_MAX_DURATION_SECONDS_PER_RUN', 20);
define('WP_UMBRELLA_ACTIVITY_LOG_OPTION_LAST_SYNC_AT', 'wp_umbrella_activity_log_last_sync_at');
define('WP_UMBRELLA_ACTIVITY_LOG_OPTION_LAST_SYNC_STATUS', 'wp_umbrella_activity_log_last_sync_status');
define('WP_UMBRELLA_ACTIVITY_LOG_TRANSIENT_BUFFER_COUNT', 'wp_umbrella_activity_log_buffer_count');

add_action(SyncScheduler::ACTION_HOOK, 'wp_umbrella_activity_log_sync_handle', 10);

/**
 * Handles the recurring activity log sync action.
 *
 * Drains the buffer in a bounded loop so a single run can absorb several
 * batches when the site produces events faster than one batch per cron tick.
 * Stops on any error (network, 429, 4xx, 5xx) so the next run retries.
 *
 * @return void
 */
function wp_umbrella_activity_log_sync_handle()
{
    $buffer = new EventBuffer();

    wp_umbrella_activity_log_enforce_buffer_cap($buffer);

    $startedAt = microtime(true);
    $finalStatus = 'success';

    for ($iteration = 0; $iteration < WP_UMBRELLA_ACTIVITY_LOG_MAX_ITERATIONS_PER_RUN; $iteration++) {
        $elapsed = microtime(true) - $startedAt;
        if ($elapsed >= WP_UMBRELLA_ACTIVITY_LOG_MAX_DURATION_SECONDS_PER_RUN) {
            ActivityLogLogger::info('Activity log sync stopped: time budget exceeded', [
                'iteration' => $iteration,
                'elapsedSeconds' => $elapsed,
            ]);
            break;
        }

        $outcome = wp_umbrella_activity_log_sync_one_batch($buffer);

        if ($outcome === 'empty') {
            break;
        }

        if ($outcome === 'success') {
            continue;
        }

        $finalStatus = $outcome;
        break;
    }

    wp_umbrella_activity_log_record_sync_result($finalStatus, $buffer);
}

/**
 * Drains and POSTs a single batch. Returns one of:
 *   - 'empty'         buffer empty, nothing to do
 *   - 'success'       2xx response, batch deleted, safe to drain another batch
 *   - 'error_network' wp_remote_post failed twice, batch preserved, stop run
 *   - 'error_429'     rate limited, batch preserved, stop run
 *   - 'error_4xx'     4xx response, batch preserved or dropped (bad_payload),
 *                     stop run regardless to avoid hammering the API
 *   - 'error_5xx'     server error, batch preserved, stop run
 *
 * @param EventBuffer $buffer
 *
 * @return string
 */
function wp_umbrella_activity_log_sync_one_batch(EventBuffer $buffer)
{
    $rows = $buffer->drain(WP_UMBRELLA_ACTIVITY_LOG_BATCH_SIZE);

    if (empty($rows)) {
        return 'empty';
    }

    $events = [];
    $ids = [];

    foreach ($rows as $row) {
        if (!isset($row['payload']) || !is_array($row['payload'])) {
            continue;
        }

        $events[] = $row['payload'];
        $ids[] = (int) $row['id'];
    }

    if (empty($events)) {
        $buffer->delete($ids);
        return 'success';
    }

    $response = wp_umbrella_activity_log_post_batch($events);

    if (is_wp_error($response)) {
        $response = wp_umbrella_activity_log_post_batch($events);
    }

    if (is_wp_error($response)) {
        ActivityLogLogger::warning('Activity log sync failed (network)', [
            'message' => $response->get_error_message(),
        ]);
        return 'error_network';
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $bodyCode = isset($body['code']) && is_string($body['code']) ? $body['code'] : null;

    if ($status >= 200 && $status < 300) {
        $buffer->delete($ids);
        return 'success';
    }

    if ($status === 429 || $bodyCode === 'limit_reached') {
        ActivityLogLogger::info('Activity log sync rate limited', ['status' => $status, 'code' => $bodyCode]);
        return 'error_429';
    }

    if ($status >= 400 && $status < 500) {
        if ($bodyCode === 'bad_payload' || $bodyCode === 'missing_parameters') {
            ActivityLogLogger::warning('Activity log sync rejected, dropping batch', [
                'status' => $status,
                'code' => $bodyCode,
                'count' => count($ids),
            ]);
            $buffer->delete($ids);
        } else {
            ActivityLogLogger::warning('Activity log sync 4xx, preserving batch', [
                'status' => $status,
                'code' => $bodyCode,
            ]);
        }
        return 'error_4xx';
    }

    ActivityLogLogger::warning('Activity log sync 5xx', ['status' => $status]);
    return 'error_5xx';
}

/**
 * POSTs an event batch to the worker activity-log route.
 *
 * @param array $events
 *
 * @return array|\WP_Error
 */
function wp_umbrella_activity_log_post_batch(array $events)
{
    return wp_umbrella_handle_outbound_response(wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/activity-log', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => sprintf('Bearer %s', wp_umbrella_get_outbound_bearer()),
            'X-Project' => site_url(),
            'X-Project-Id' => wp_umbrella_get_project_id(),
            'X-Secret-Token' => wp_umbrella_get_secret_token(),
        ],
        'body' => wp_json_encode(['events' => $events]),
        'timeout' => 15,
    ]));
}

/**
 * Persists the latest sync attempt result for observability.
 *
 * @param string      $status
 * @param EventBuffer $buffer
 *
 * @return void
 */
function wp_umbrella_activity_log_record_sync_result($status, EventBuffer $buffer)
{
    update_option(WP_UMBRELLA_ACTIVITY_LOG_OPTION_LAST_SYNC_AT, gmdate('Y-m-d H:i:s'), false);
    update_option(WP_UMBRELLA_ACTIVITY_LOG_OPTION_LAST_SYNC_STATUS, $status, false);
    set_transient(WP_UMBRELLA_ACTIVITY_LOG_TRANSIENT_BUFFER_COUNT, $buffer->count(), 10 * MINUTE_IN_SECONDS);
}

/**
 * Two-stage buffer cleanup:
 *   1. drop events older than the configured TTL (prevents zombie events
 *      from a permanently broken sync from hogging buffer space forever)
 *   2. cap by row count, dropping the oldest excess rows
 *
 * Both stages are no-ops by default if the corresponding filter returns 0.
 *
 * @param EventBuffer $buffer
 *
 * @return void
 */
function wp_umbrella_activity_log_enforce_buffer_cap(EventBuffer $buffer)
{
    wp_umbrella_activity_log_enforce_buffer_ttl($buffer);
    wp_umbrella_activity_log_enforce_buffer_count_cap($buffer);
}

/**
 * Drops events older than the configured TTL (default 7 days).
 *
 * @param EventBuffer $buffer
 *
 * @return void
 */
function wp_umbrella_activity_log_enforce_buffer_ttl(EventBuffer $buffer)
{
    $maxAgeDays = (int) apply_filters(
        'wp_umbrella_activity_log_buffer_max_age_days',
        WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_AGE_DAYS
    );

    if ($maxAgeDays <= 0) {
        return;
    }

    $cutoffTimestamp = time() - ($maxAgeDays * DAY_IN_SECONDS);
    $cutoff = gmdate('Y-m-d H:i:s', $cutoffTimestamp);

    $deleted = $buffer->deleteOlderThan($cutoff);

    if ($deleted > 0) {
        ActivityLogLogger::warning('Activity log buffer TTL expired, old rows dropped', [
            'maxAgeDays' => $maxAgeDays,
            'cutoff' => $cutoff,
            'dropped' => $deleted,
        ]);
    }
}

/**
 * Drops the oldest rows when the buffer exceeds the configured cap. Last
 * line of defence against unbounded growth on misconfigured sites.
 *
 * @param EventBuffer $buffer
 *
 * @return void
 */
function wp_umbrella_activity_log_enforce_buffer_count_cap(EventBuffer $buffer)
{
    $cap = (int) apply_filters(
        'wp_umbrella_activity_log_buffer_max_rows',
        WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_ROWS
    );

    if ($cap <= 0) {
        return;
    }

    $count = $buffer->count();

    if ($count <= $cap) {
        return;
    }

    $excess = $count - $cap;
    $rowsToDrop = $buffer->drain($excess);

    $ids = [];
    foreach ($rowsToDrop as $row) {
        if (isset($row['id'])) {
            $ids[] = (int) $row['id'];
        }
    }

    if (empty($ids)) {
        return;
    }

    $buffer->delete($ids);

    ActivityLogLogger::warning('Activity log buffer cap exceeded, oldest rows dropped', [
        'cap' => $cap,
        'previousCount' => $count,
        'dropped' => count($ids),
    ]);
}
