<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Records a WP Umbrella protection event into the activity log buffer.
 *
 * Unlike the sensors, these events are produced by WP Umbrella itself to
 * surface the protective actions taken on the site. They are written straight
 * to the buffer so they are captured even when the customer activity log is
 * turned off, and drained by the same sync scheduler.
 */
class ProtectionEventRecorder
{
    /**
     * @var EventBuffer
     */
    protected $buffer;

    /**
     * @param EventBuffer|null $buffer
     */
    public function __construct(?EventBuffer $buffer = null)
    {
        $this->buffer = $buffer !== null ? $buffer : new EventBuffer();
    }

    /**
     * @param string $eventKey
     * @param string $severity
     * @param array  $context
     *
     * @return void
     */
    public function record($eventKey, $severity, array $context = [])
    {
        $payload = [
            'eventKey' => $eventKey,
            'severity' => $severity,
            'channel' => ChannelResolver::resolve(),
            'clientIp' => ClientIpResolver::resolve(),
            'siteId' => function_exists('is_multisite') && is_multisite() && function_exists('get_current_blog_id')
                ? get_current_blog_id()
                : null,
            'wpUserId' => null,
            'wpUsername' => null,
            'wpUserRoles' => [],
            'occurredAt' => self::nowWithMilliseconds(),
            'context' => $context,
        ];

        $this->buffer->insert([
            'eventKey' => $eventKey,
            'severity' => $severity,
            'channel' => $payload['channel'],
            'occurredAt' => $payload['occurredAt'],
            'payload' => $payload,
        ]);
    }

    /**
     * Records a protection event at most once per window, accumulating the
     * number of occurrences in a transient bucket between emissions.
     *
     * @param string $eventKey
     * @param string $severity
     * @param array  $context
     * @param string $bucketKey
     * @param int    $window
     *
     * @return void
     */
    public function recordAggregated($eventKey, $severity, array $context, $bucketKey, $window)
    {
        $now = time();
        $state = get_transient($bucketKey);

        if (!is_array($state) || !isset($state['count'], $state['emittedAt'])) {
            $this->record($eventKey, $severity, array_merge($context, ['attempts' => '1']));
            set_transient($bucketKey, ['count' => 0, 'emittedAt' => $now], $window);
            return;
        }

        $count = (int) $state['count'] + 1;
        $emittedAt = (int) $state['emittedAt'];

        if (($now - $emittedAt) >= $window) {
            $this->record($eventKey, $severity, array_merge($context, ['attempts' => (string) $count]));
            set_transient($bucketKey, ['count' => 0, 'emittedAt' => $now], $window);
            return;
        }

        set_transient($bucketKey, ['count' => $count, 'emittedAt' => $emittedAt], $window);
    }

    /**
     * @return string
     */
    protected static function nowWithMilliseconds()
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
}
