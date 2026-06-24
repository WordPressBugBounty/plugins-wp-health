<?php

namespace WPUmbrella\Actions\ActivityLog\Framework;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Base class for every activity log sensor.
 *
 * Each sensor declares the WordPress hooks it listens to inside register()
 * and emits events through recordEvent(). The base class handles the common
 * concerns: self referential protection, noise filtering, context capture
 * (channel, IP, user, multisite blog id, occurredAt), and persistence in the
 * EventBuffer.
 */
abstract class AbstractSensor
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
     * Subclasses register their WP hooks here.
     *
     * @return void
     */
    abstract public function register();

    /**
     * Records an event after applying self referential protection,
     * noise filtering and automatic context enrichment.
     *
     * @param string $eventKey
     * @param string $severity
     * @param array  $context
     *
     * @return void
     */
    protected function recordEvent($eventKey, $severity, array $context = [])
    {
        ActivityLogLogger::debug('Event captured', [
            'eventKey' => $eventKey,
            'severity' => $severity,
            'sensor' => static::class,
        ]);

        if (WPUmbrellaContext::isActing()) {
            ActivityLogLogger::debug('Event skipped: WPU acting', ['eventKey' => $eventKey]);
            return;
        }

        if (NoiseFilter::shouldSkip($eventKey, $context)) {
            ActivityLogLogger::debug('Event skipped: noise filter', [
                'eventKey' => $eventKey,
                'context' => $context,
            ]);
            return;
        }

        $userContext = $this->getCurrentUserContext();

        $payload = [
            'eventKey' => $eventKey,
            'severity' => $severity,
            'channel' => ChannelResolver::resolve(),
            'clientIp' => ClientIpResolver::resolve(),
            'siteId' => function_exists('is_multisite') && is_multisite() && function_exists('get_current_blog_id')
                ? get_current_blog_id()
                : null,
            'wpUserId' => $userContext['wpUserId'],
            'wpUsername' => $userContext['wpUsername'],
            'wpUserRoles' => $userContext['wpUserRoles'],
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
     * Returns the current WP user data, or null fields when nobody is logged in.
     *
     * @return array
     */
    protected function getCurrentUserContext()
    {
        if (!function_exists('wp_get_current_user') || !function_exists('get_current_user_id')) {
            return [
                'wpUserId' => null,
                'wpUsername' => null,
                'wpUserRoles' => [],
            ];
        }

        $userId = (int) get_current_user_id();

        if ($userId <= 0) {
            return [
                'wpUserId' => null,
                'wpUsername' => null,
                'wpUserRoles' => [],
            ];
        }

        $user = wp_get_current_user();

        return [
            'wpUserId' => $userId,
            'wpUsername' => isset($user->user_login) ? $user->user_login : null,
            'wpUserRoles' => isset($user->roles) && is_array($user->roles) ? array_values($user->roles) : [],
        ];
    }

    /**
     * Returns the current UTC timestamp with millisecond precision,
     * formatted as Y-m-d H:i:s.v (compatible with DATETIME(3)).
     *
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
