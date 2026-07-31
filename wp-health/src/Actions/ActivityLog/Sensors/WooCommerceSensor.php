<?php

namespace WPUmbrella\Actions\ActivityLog\Sensors;

use WPUmbrella\Actions\ActivityLog\Framework\AbstractSensor;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

/**
 * Proof of concept: captures WooCommerce payment fraud signals (card testing,
 * repeated failed orders) so the community IP reputation can learn from them.
 *
 * Event keys emitted:
 * - security.woocommerce.payment_failed   (MEDIUM)
 *
 * The order stores its own customer IP, which is the offender, so it is
 * carried under offenderIp rather than the request IP.
 */
class WooCommerceSensor extends AbstractSensor
{
    /**
     * @return void
     */
    public function register()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_order_status_failed', [$this, 'onOrderFailed'], 10, 1);
    }

    /**
     * @param int $orderId
     *
     * @return void
     */
    public function onOrderFailed($orderId = 0)
    {
        $orderId = (int) $orderId;

        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);

        if (!is_object($order) || !method_exists($order, 'get_customer_ip_address')) {
            return;
        }

        $ip = $order->get_customer_ip_address();

        $this->recordEvent('security.woocommerce.payment_failed', 'MEDIUM', [
            'orderId' => $orderId,
            'offenderIp' => is_string($ip) && $ip !== '' ? $ip : null,
        ]);
    }
}
