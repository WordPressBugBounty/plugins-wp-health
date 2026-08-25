<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Captures XML-RPC abuse signals (multicall amplification, pingback floods).
 *
 * Event keys emitted:
 * - security.xmlrpc.abuse   (MEDIUM)
 *
 * The request IP is the offender, so no offenderIp metadata is needed.
 *
 * Occurrences are tallied in memory and written as one buffered row per method
 * at the end of the request, carrying the count.
 */
class XmlRpcSensor extends AbstractSensor
{
    const ABUSE_METHODS = ['system.multicall', 'pingback.ping'];

    /**
     * @var array<string, int>
     */
    protected $counts = [];

    /**
     * @var bool
     */
    protected $flushScheduled = false;

    /**
     * @return void
     */
    public function register()
    {
        add_action('xmlrpc_call', [$this, 'onXmlRpcCall'], 10, 1);
    }

    /**
     * @param string $method
     *
     * @return void
     */
    public function onXmlRpcCall($method = '')
    {
        if (!is_string($method) || !in_array($method, self::ABUSE_METHODS, true)) {
            return;
        }

        if (!isset($this->counts[$method])) {
            $this->counts[$method] = 0;
        }

        $this->counts[$method]++;

        if (!$this->flushScheduled) {
            add_action('shutdown', [$this, 'flush'], 10, 0);
            $this->flushScheduled = true;
        }
    }

    /**
     * @return void
     */
    public function flush()
    {
        foreach ($this->counts as $method => $count) {
            $this->recordEvent('security.xmlrpc.abuse', 'MEDIUM', [
                'method' => $method,
                'attempts' => (string) $count,
            ]);
        }

        $this->counts = [];
    }
}
