<?php

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

use WPUmbrella\Actions\ActivityLog\Framework\ActivityLogLogger;
use WPUmbrella\Actions\ActivityLog\Framework\EventBuffer;
use WPUmbrella\Actions\ActivityLog\Framework\PayloadLimits;
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
define('WP_UMBRELLA_ACTIVITY_LOG_BUFFER_TRIM_BATCH', 5000);
define('WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_BYTES', 16777216);
define('WP_UMBRELLA_ACTIVITY_LOG_BUFFER_SIZE_TRIM_BATCH', 500);
define('WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_AGE_DAYS', 7);
define('WP_UMBRELLA_ACTIVITY_LOG_MAX_ITERATIONS_PER_RUN', 10);
define('WP_UMBRELLA_ACTIVITY_LOG_MAX_DURATION_SECONDS_PER_RUN', 20);
define('WP_UMBRELLA_ACTIVITY_LOG_RUN_RESERVE_SECONDS', 5);
define('WP_UMBRELLA_ACTIVITY_LOG_POST_TIMEOUT_SECONDS', 15);
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
 * The buffer cleanup runs after the loop so every row gets offered to the
 * drain before it can be dropped.
 *
 * The first batch always runs. Every later one has to fit in what is left of
 * the budget, measured against the slowest batch of the run, so the loop never
 * starts work it has no time to finish.
 *
 * @return void
 */
function wp_umbrella_activity_log_sync_handle()
{
    $buffer = new EventBuffer();

    $startedAt = microtime(true);
    $budget = wp_umbrella_activity_log_run_budget();
    $slowestIteration = 0.0;
    $finalStatus = 'success';

    for ($iteration = 0; $iteration < WP_UMBRELLA_ACTIVITY_LOG_MAX_ITERATIONS_PER_RUN; $iteration++) {
        $elapsed = microtime(true) - $startedAt;

        if ($iteration > 0 && ($elapsed + $slowestIteration) >= $budget) {
            ActivityLogLogger::info('Activity log sync stopped: time budget exceeded', [
                'iteration' => $iteration,
                'elapsedSeconds' => $elapsed,
                'budgetSeconds' => $budget,
            ]);
            break;
        }

        $iterationStartedAt = microtime(true);
        $outcome = wp_umbrella_activity_log_sync_one_batch($buffer);
        $iterationDuration = microtime(true) - $iterationStartedAt;

        if ($iterationDuration > $slowestIteration) {
            $slowestIteration = $iterationDuration;
        }

        if ($outcome === 'empty') {
            break;
        }

        if ($outcome === 'success') {
            continue;
        }

        $finalStatus = $outcome;
        break;
    }

    wp_umbrella_activity_log_enforce_buffer_cap($buffer);

    wp_umbrella_activity_log_record_sync_result($finalStatus, $buffer);
}

/**
 * Seconds the drain loop is allowed to spend, capped by our own ceiling and by
 * what is left of the host execution limit once a reserve is kept for the
 * buffer cleanup that follows the loop.
 *
 * A limit of 0 means the host sets none, in which case only our ceiling applies.
 * The elapsed time is measured from the start of the request, which understates
 * what is left whenever the runner has raised the limit, so the result errs on
 * the low side.
 *
 * @return float
 */
function wp_umbrella_activity_log_run_budget()
{
    $ceiling = (float) WP_UMBRELLA_ACTIVITY_LOG_MAX_DURATION_SECONDS_PER_RUN;
    $limit = (int) ini_get('max_execution_time');

    if ($limit <= 0) {
        return $ceiling;
    }

    $consumed = 0.0;

    if (isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT'])) {
        $consumed = max(0.0, microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']);
    }

    $remaining = $limit - $consumed - WP_UMBRELLA_ACTIVITY_LOG_RUN_RESERVE_SECONDS;

    if ($remaining >= $ceiling) {
        return $ceiling;
    }

    return max(0.0, $remaining);
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
    $rows = $buffer->drain(WP_UMBRELLA_ACTIVITY_LOG_BATCH_SIZE, PayloadLimits::MAX_BATCH_BYTES);

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
        'timeout' => WP_UMBRELLA_ACTIVITY_LOG_POST_TIMEOUT_SECONDS,
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
 * Three-stage buffer cleanup, run once the drain loop has had its turn on
 * every row it could deliver:
 *   1. drop events older than the configured TTL (prevents zombie events
 *      from a permanently broken sync from hogging buffer space forever)
 *   2. cap by row count, dropping the oldest excess rows
 *   3. cap by stored volume, dropping the oldest rows
 *
 * Every stage is a no-op if the corresponding filter returns 0, and the whole
 * cleanup is skipped when the drain emptied the buffer.
 *
 * @param EventBuffer $buffer
 *
 * @return void
 */
function wp_umbrella_activity_log_enforce_buffer_cap(EventBuffer $buffer)
{
    if ($buffer->count() === 0) {
        return;
    }

    wp_umbrella_activity_log_enforce_buffer_ttl($buffer);
    wp_umbrella_activity_log_enforce_buffer_count_cap($buffer);
    wp_umbrella_activity_log_enforce_buffer_size_cap($buffer);
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

    // Bounded per run. Successive runs converge.
    $excess = min($count - $cap, WP_UMBRELLA_ACTIVITY_LOG_BUFFER_TRIM_BATCH);

    $dropped = $buffer->deleteOldest($excess);

    if ($dropped === 0) {
        return;
    }

    ActivityLogLogger::warning('Activity log buffer cap exceeded, oldest rows dropped', [
        'cap' => $cap,
        'previousCount' => $count,
        'dropped' => $dropped,
    ]);
}

/**
 * Drops just enough of the oldest rows to bring the stored payload volume
 * back under the configured cap. Bounded per run, successive runs converge.
 *
 * @param EventBuffer $buffer
 *
 * @return void
 */
function wp_umbrella_activity_log_enforce_buffer_size_cap(EventBuffer $buffer)
{
    $cap = (int) apply_filters(
        'wp_umbrella_activity_log_buffer_max_bytes',
        WP_UMBRELLA_ACTIVITY_LOG_BUFFER_MAX_BYTES
    );

    if ($cap <= 0) {
        return;
    }

    $bytes = $buffer->totalPayloadBytes();

    if ($bytes <= $cap) {
        return;
    }

    $dropped = $buffer->deleteOldestBytes(
        $bytes - $cap,
        WP_UMBRELLA_ACTIVITY_LOG_BUFFER_SIZE_TRIM_BATCH
    );

    if ($dropped === 0) {
        return;
    }

    ActivityLogLogger::warning('Activity log buffer size cap exceeded, oldest rows dropped', [
        'cap' => $cap,
        'previousBytes' => $bytes,
        'dropped' => $dropped,
    ]);
}
