<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Local DB persistence for activity log events.
 *
 * Sensors push rows here via insert(). The sync scheduler drains the buffer
 * in FIFO order via drain() and removes the rows via delete() once the API
 * has acknowledged them.
 */
class EventBuffer
{
    /**
     * Inserts a single event into the buffer.
     *
     * Expected keys: eventKey, severity, channel, occurredAt, payload (array).
     *
     * @param array $event
     *
     * @return void
     */
    public function insert(array $event)
    {
        global $wpdb;

        SchemaInstaller::ensureTableExists();

        $eventKey = isset($event['eventKey']) ? (string) $event['eventKey'] : '';
        $severity = isset($event['severity']) ? (string) $event['severity'] : '';
        $channel = isset($event['channel']) ? (string) $event['channel'] : '';
        $occurredAt = isset($event['occurredAt']) ? (string) $event['occurredAt'] : '';
        $payload = isset($event['payload']) ? $event['payload'] : [];

        if ($eventKey === '' || $severity === '' || $channel === '' || $occurredAt === '') {
            ActivityLogLogger::warning('Event rejected: missing required field', [
                'eventKey' => $eventKey,
                'severity' => $severity,
                'channel' => $channel,
                'occurredAt' => $occurredAt,
            ]);
            return;
        }

        $encoded = wp_json_encode($payload);

        if ($encoded === false) {
            ActivityLogLogger::warning('Event rejected: payload not JSON encodable', [
                'eventKey' => $eventKey,
            ]);
            return;
        }

        $inserted = $wpdb->insert(
            SchemaInstaller::getTableName(),
            [
                'event_key' => $eventKey,
                'severity' => $severity,
                'channel' => $channel,
                'occurred_at' => $occurredAt,
                'payload' => $encoded,
                'created_at' => self::nowUtc(),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            ActivityLogLogger::warning('Event buffer insert failed', [
                'eventKey' => $eventKey,
                'dbError' => $wpdb->last_error,
            ]);
            return;
        }

        ActivityLogLogger::debug('Event buffered', [
            'id' => (int) $wpdb->insert_id,
            'eventKey' => $eventKey,
            'severity' => $severity,
            'channel' => $channel,
        ]);
    }

    /**
     * Returns the current UTC timestamp formatted as Y-m-d H:i:s.v.
     *
     * The MySQL session timezone is unknown and may differ between hosts, so
     * we never rely on CURRENT_TIMESTAMP. Every datetime written to the
     * buffer is a UTC value produced by gmdate() to keep the buffer 100% UTC.
     *
     * @return string
     */
    protected static function nowUtc()
    {
        $microtime = microtime(true);
        $seconds = (int) floor($microtime);
        $milliseconds = (int) round(($microtime - $seconds) * 1000);

        if ($milliseconds >= 1000) {
            $seconds += 1;
            $milliseconds = 0;
        }

        return gmdate('Y-m-d H:i:s', $seconds) . '.' . str_pad((string) $milliseconds, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Returns up to $batchSize events in FIFO order (oldest first).
     *
     * Each row is returned with payload already decoded.
     *
     * @param int $batchSize
     *
     * @return array
     */
    public function drain($batchSize)
    {
        global $wpdb;

        SchemaInstaller::ensureTableExists();

        $batchSize = max(1, (int) $batchSize);
        $tableName = SchemaInstaller::getTableName();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, event_key, severity, channel, occurred_at, payload, created_at
                 FROM {$tableName}
                 ORDER BY occurred_at ASC, id ASC
                 LIMIT %d",
                $batchSize
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['payload'] = isset($row['payload']) ? json_decode($row['payload'], true) : null;
        }

        return $rows;
    }

    /**
     * Deletes the rows whose ids are in the provided array.
     *
     * @param array $ids
     *
     * @return void
     */
    public function delete(array $ids)
    {
        global $wpdb;

        if (empty($ids)) {
            return;
        }

        SchemaInstaller::ensureTableExists();

        $sanitized = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $sanitized[] = $intId;
            }
        }

        if (empty($sanitized)) {
            return;
        }

        $tableName = SchemaInstaller::getTableName();
        $placeholders = implode(',', array_fill(0, count($sanitized), '%d'));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$tableName} WHERE id IN ({$placeholders})",
                $sanitized
            )
        );
    }

    /**
     * Deletes events whose occurred_at is older than the given UTC cutoff.
     *
     * @param string $cutoff UTC timestamp formatted Y-m-d H:i:s
     *
     * @return int Number of rows deleted
     */
    public function deleteOlderThan($cutoff)
    {
        global $wpdb;

        SchemaInstaller::ensureTableExists();

        $cutoff = (string) $cutoff;

        if ($cutoff === '') {
            return 0;
        }

        $tableName = SchemaInstaller::getTableName();

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$tableName} WHERE occurred_at < %s",
                $cutoff
            )
        );

        return $deleted === false ? 0 : (int) $deleted;
    }

    /**
     * Removes every row from the buffer. Used by the support page maintenance
     * action when the operator wants to drop all pending events without
     * waiting for the sync to drain them.
     *
     * @return int Number of rows deleted
     */
    public function clear()
    {
        global $wpdb;

        SchemaInstaller::ensureTableExists();

        $tableName = SchemaInstaller::getTableName();
        $deleted = $wpdb->query("DELETE FROM {$tableName}");

        return $deleted === false ? 0 : (int) $deleted;
    }

    /**
     * Number of events currently buffered.
     *
     * @return int
     */
    public function count()
    {
        global $wpdb;

        SchemaInstaller::ensureTableExists();

        $tableName = SchemaInstaller::getTableName();
        $result = $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}");

        return (int) $result;
    }
}
