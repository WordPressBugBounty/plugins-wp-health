<?php
namespace WPUmbrella\Services;

class NetworkIdentity
{
    const OPTION_NAME = 'wp_umbrella_network_uuid';

    public function getNetworkUuid()
    {
        $uuid = get_site_option(self::OPTION_NAME);

        if (!empty($uuid)) {
            return (string) $uuid;
        }

        add_site_option(self::OPTION_NAME, wp_generate_uuid4());

        return $this->keepFirstNetworkUuid();
    }

    protected function keepFirstNetworkUuid()
    {
        global $wpdb;

        $networkId = (int) get_current_network_id();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_id, meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
            $networkId,
            self::OPTION_NAME
        ));

        if (empty($row)) {
            return (string) get_site_option(self::OPTION_NAME);
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s AND meta_id <> %d",
            $networkId,
            self::OPTION_NAME,
            (int) $row->meta_id
        ));

        wp_cache_delete($networkId . ':' . self::OPTION_NAME, 'site-options');

        return (string) $row->meta_value;
    }

    /**
     * @return array Empty on a single site install.
     */
    public function getData()
    {
        if (!is_multisite()) {
            return [];
        }

        $mainBlogId = (int) get_main_site_id();

        return [
            'network_uuid' => $this->getNetworkUuid(),
            'blog_id' => (int) get_current_blog_id(),
            'is_main_site' => (bool) is_main_site(),
            'wp_network_id' => (int) get_current_network_id(),
            'main_blog_id' => $mainBlogId,
            'main_site_url' => get_site_url($mainBlogId),
            'network_url' => network_site_url(),
        ];
    }
}
