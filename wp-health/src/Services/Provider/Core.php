<?php
namespace WPUmbrella\Services\Provider;

if (!defined('ABSPATH')) {
    exit;
}

class Core
{
    const NAME_SERVICE = 'CoreProvider';

    public function getCoreVersions()
    {
        $locale = get_locale();
        $response = wp_remote_get("https://api.wordpress.org/core/version-check/1.7/?locale={$locale}");
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body);

        $versions = [];
        if (!$body || !property_exists($body, 'offers')) {
            return $versions;
        }

        $offers = $body->offers;

        if (!$offers) {
            return $versions;
        }

        foreach ($offers as $offer) {
            if (version_compare($offer->version, '4.0', '>=')) {
                $offer->response = 'latest';
                $versions[$offer->version] = $offer;
            }
        }

        return array_values($versions);
    }
}
