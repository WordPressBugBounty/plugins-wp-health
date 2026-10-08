<?php
namespace WPUmbrella\Services\Provider;

class Network
{
    const NAME_SERVICE = 'NetworkProvider';

    const PER_PAGE = 100;

    public function getData($page)
    {
        $page = max(1, (int) $page);
        $query = [
            'network_id' => get_current_network_id(),
            'orderby' => 'id',
            'order' => 'ASC',
        ];

        $sites = get_sites(array_merge($query, [
            'number' => self::PER_PAGE,
            'offset' => ($page - 1) * self::PER_PAGE,
        ]));

        return [
            'network' => $this->getNetwork(),
            'sites' => array_map([$this, 'getSite'], $sites),
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total' => (int) get_sites(array_merge($query, ['count' => true])),
        ];
    }

    protected function getNetwork()
    {
        global $wpdb;

        $identity = wp_umbrella_get_service('NetworkIdentity')->getData();
        unset($identity['blog_id'], $identity['is_main_site']);

        return array_merge($identity, [
            'subdomain_install' => (bool) is_subdomain_install(),
            'base_prefix' => $wpdb->base_prefix,
            'ms_files_rewriting' => (bool) get_site_option('ms_files_rewriting'),
            'site_count' => (int) get_blog_count(),
            'is_large_network' => (bool) wp_is_large_network(),
            'network_active_plugins' => array_keys((array) get_site_option('active_sitewide_plugins', [])),
        ]);
    }

    /**
     * @param \WP_Site $site
     * @return array
     */
    protected function getSite($site)
    {
        $blogId = (int) $site->blog_id;
        $switched = get_current_blog_id() !== $blogId;

        if ($switched) {
            switch_to_blog($blogId);
        }

        try {
            return [
                'blog_id' => $blogId,
                'domain' => $site->domain,
                'path' => $site->path,
                'site_url' => get_option('siteurl'),
                'home_url' => get_option('home'),
                'name' => get_option('blogname'),
                'is_main_site' => $blogId === (int) get_main_site_id(),
                'public' => (bool) $site->public,
                'archived' => (bool) $site->archived,
                'deleted' => (bool) $site->deleted,
                'spam' => (bool) $site->spam,
                'stylesheet' => get_option('stylesheet'),
                'template' => get_option('template'),
                'active_plugins' => array_values((array) get_option('active_plugins', [])),
            ];
        } finally {
            if ($switched) {
                restore_current_blog();
            }
        }
    }
}
