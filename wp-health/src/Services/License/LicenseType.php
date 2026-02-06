<?php
namespace WPUmbrella\Services\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License type constants
 */
final class LicenseType
{
    const EDD = 'edd';
    const WOOCOMMERCE = 'woocommerce';
    const ENVATO = 'envato';
    const FREEMIUS = 'freemius';
    const CUSTOM = 'custom';
}
