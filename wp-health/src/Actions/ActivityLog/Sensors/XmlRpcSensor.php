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
 */
class XmlRpcSensor extends AbstractSensor
{
    const ABUSE_METHODS = ['system.multicall', 'pingback.ping'];

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

        $this->recordEvent('security.xmlrpc.abuse', 'MEDIUM', [
            'method' => $method,
        ]);
    }
}
