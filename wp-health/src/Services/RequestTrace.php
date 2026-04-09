<?php
namespace WPUmbrella\Services;

/**
 * Collects breadcrumb-style trace markers during an API request.
 *
 * Usage:
 *   wp_umbrella_get_service('RequestTrace')->addTrace('backup_started');
 *   wp_umbrella_get_service('RequestTrace')->addTrace('bulk_upgrade_done');
 *
 * The trace is automatically appended to every API response via AbstractController::returnResponse().
 * The worker logs it to OTel for end-to-end update tracing.
 */
class RequestTrace
{
    const NAME_SERVICE = 'RequestTrace';

    /** @var array[] */
    protected static $entries = [];

    /** @var float */
    protected static $startTime = 0;

    public function __construct()
    {
        if (self::$startTime === 0) {
            self::$startTime = microtime(true);
        }
    }

    /**
     * Add a trace marker at the current point in execution.
     *
     * @param string $label Short identifier (e.g. 'backup_started', 'bulk_upgrade_done')
     * @param array  $meta  Optional key-value metadata
     */
    public function addTrace($label, $meta = [])
    {
        $entry = [
            'label' => $label,
            'elapsed_ms' => round((microtime(true) - self::$startTime) * 1000),
        ];

        if (!empty($meta)) {
            $entry['meta'] = $meta;
        }

        self::$entries[] = $entry;
    }

    /**
     * Get all trace entries. Returns null if no traces were recorded.
     *
     * @return array[]|null
     */
    public function getTrace()
    {
        if (empty(self::$entries)) {
            return null;
        }

        return self::$entries;
    }

    /**
     * Reset the trace (for reuse across service locator calls).
     */
    public function reset()
    {
        self::$entries = [];
        self::$startTime = microtime(true);
    }
}
