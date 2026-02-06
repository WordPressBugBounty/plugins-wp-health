<?php
namespace WPUmbrella\Services\License\Detectors;

use WPUmbrella\Services\License\LicenseStatus;
use WPUmbrella\Services\License\LicenseType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * License detector for WooCommerce extensions (Subscriptions, etc.)
 */
class WooCommerceExtensionLicenseDetector extends AbstractLicenseDetector
{
    protected $pluginSlug = 'woocommerce-subscriptions/woocommerce-subscriptions.php';
    protected $pluginName = 'WooCommerce Subscriptions';
    protected $licenseType = LicenseType::WOOCOMMERCE;

    /**
     * {@inheritdoc}
     */
    public function detect(array $options = []): ?array
    {
        // WooCommerce extensions use wc_helper for license management
        $authData = get_option('woocommerce_helper_data');

        if (empty($authData) || !is_array($authData)) {
            return null;
        }

        // Get subscription data
        $subscriptions = get_transient('_woocommerce_helper_subscriptions');

        if (empty($subscriptions)) {
            return null;
        }

        $pluginSubscription = $this->findPluginSubscription($subscriptions);

        if ($pluginSubscription === null) {
            return null;
        }

        $status = isset($pluginSubscription['expired']) && !$pluginSubscription['expired']
            ? LicenseStatus::VALID
            : LicenseStatus::EXPIRED;

        return $this->buildLicenseData(
            $pluginSubscription['product_key'] ?? '',
            $status,
            [
                'license_expires' => $pluginSubscription['expires'] ?? null,
                'extra_data' => [
                    'product_id' => $pluginSubscription['product_id'] ?? null,
                ],
            ]
        );
    }

    /**
     * Find subscription for this specific plugin
     *
     * @param array $subscriptions
     * @return array|null
     */
    private function findPluginSubscription(array $subscriptions): ?array
    {
        $slug = dirname($this->pluginSlug);

        foreach ($subscriptions as $subscription) {
            if (!isset($subscription['product_slug'])) {
                continue;
            }

            if (strpos($subscription['product_slug'], $slug) !== false) {
                return $subscription;
            }
        }

        return null;
    }
}
