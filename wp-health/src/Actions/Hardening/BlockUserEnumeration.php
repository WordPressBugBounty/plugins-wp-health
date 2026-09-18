<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Actions\ActivityLog\Framework\ProtectionEventRecorder;
use WPUmbrella\Actions\ActivityLog\Framework\SyncScheduler;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class BlockUserEnumeration implements ExecuteHooks
{
    const BLOCK_EVENT_KEY = 'umbrella.protection.user_enumeration_blocked';

    const BLOCK_BUCKET_KEY = 'wp_umbrella_user_enum_block_bucket';

    const BLOCK_WINDOW = 1800;

    const DEFAULT_SITEMAP_FILENAME = 'sitemap';

    public function hooks()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('block_user_enumeration')) {
            return;
        }

        add_action('init', [$this, 'blockAuthorEnumeration'], 1);
        add_action('parse_request', [$this, 'blockAuthorArchive']);
        add_filter('rest_endpoints', [$this, 'removeUsersRestEndpoints']);
        add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemapProvider'], 10, 2);
        add_filter('wpseo_sitemap_index_links', [$this, 'removeYoastAuthorIndexes']);
        add_filter('wpseo_build_sitemap_post_type', [$this, 'excludeYoastAuthorsWhileBuilding']);
        add_filter('aioseo_sitemap_author_archives', [$this, 'removeSitemapAuthors']);
        add_filter('aioseo_sitemap_indexes', [$this, 'removeAioseoAuthorIndexes']);
        add_filter('rank_math/sitemap/index/entry', [$this, 'removeRankMathAuthorEntry'], 10, 2);
        add_filter('rank_math/sitemap/entry', [$this, 'removeRankMathAuthorEntry'], 10, 2);
        add_filter('oembed_response_data', [$this, 'maskOembedAuthor']);
        add_filter('the_author', [$this, 'maskFeedAuthor']);

        add_action('init', function () {
            (new SyncScheduler())->schedule();
        }, 20);
    }

    public function blockAuthorEnumeration()
    {
        if (!$this->shouldBlockAuthorQuery()) {
            return;
        }

        $this->blockAndRedirect();
    }

    public function blockAuthorArchive($wp)
    {
        if (!$this->shouldBlockAuthorArchive($wp)) {
            return;
        }

        $this->blockAndRedirect();
    }

    /**
     * @return bool
     */
    public function shouldBlockAuthorQuery()
    {
        if (is_admin() || $this->callerMayReadAuthors()) {
            return false;
        }

        return isset($_GET['author']);
    }

    /**
     * @param object $wp
     *
     * @return bool
     */
    public function shouldBlockAuthorArchive($wp)
    {
        if (is_admin() || $this->callerMayReadAuthors()) {
            return false;
        }

        if (!isset($wp->query_vars) || !is_array($wp->query_vars)) {
            return false;
        }

        return !empty($wp->query_vars['author_name']);
    }

    /**
     * edit_posts is the capability WordPress itself accepts on
     * /wp/v2/users?who=authors, and the roles that carry it read the author
     * list from the editor. The roster stays closed to everyone below them.
     *
     * @return bool
     */
    protected function callerMayReadAuthors()
    {
        return current_user_can('list_users') || current_user_can('edit_posts');
    }

    protected function blockAndRedirect()
    {
        (new ProtectionEventRecorder())->recordAggregated(self::BLOCK_EVENT_KEY, 'INFO', [
            'kind' => 'protection',
            'protection' => 'block_user_enumeration',
            'outcome' => 'blocked',
        ], self::BLOCK_BUCKET_KEY, self::BLOCK_WINDOW);

        wp_safe_redirect(home_url(), 302);
        exit;
    }

    public function removeUsersRestEndpoints($endpoints)
    {
        if ($this->callerMayReadAuthors()) {
            return $endpoints;
        }

        unset(
            $endpoints['/wp/v2/users'],
            $endpoints['/wp/v2/users/(?P<id>[\d]+)']
        );

        if (!is_user_logged_in()) {
            unset($endpoints['/wp/v2/users/me']);
        }

        return $endpoints;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function maskOembedAuthor($data)
    {
        if (!is_array($data) || $this->callerMayReadAuthors()) {
            return $data;
        }

        $data['author_name'] = get_bloginfo('name');
        $data['author_url'] = get_home_url();

        return $data;
    }

    /**
     * @param string $author
     *
     * @return string
     */
    public function maskFeedAuthor($author)
    {
        if (!is_feed() || $this->callerMayReadAuthors()) {
            return $author;
        }

        return get_bloginfo('name');
    }

    public function removeUsersSitemapProvider($provider, $name)
    {
        if ($name === 'users') {
            return false;
        }

        return $provider;
    }

    /**
     * @return array
     */
    public function removeSitemapAuthors()
    {
        return [];
    }

    /**
     * Yoast reads the authors as full user objects only when this filter is
     * already registered, so it is registered on the one build that needs it.
     *
     * @param string $type
     *
     * @return string
     */
    public function excludeYoastAuthorsWhileBuilding($type)
    {
        if ($type === 'author') {
            add_filter('wpseo_sitemap_exclude_author', [$this, 'removeSitemapAuthors']);
        }

        return $type;
    }

    /**
     * @param array $links
     *
     * @return array
     */
    public function removeYoastAuthorIndexes($links)
    {
        return $this->withoutAuthorSitemaps($links, self::DEFAULT_SITEMAP_FILENAME);
    }

    /**
     * @param array $indexes
     *
     * @return array
     */
    public function removeAioseoAuthorIndexes($indexes)
    {
        return $this->withoutAuthorSitemaps($indexes, $this->aioseoSitemapFilename());
    }

    /**
     * @param array  $entries
     * @param string $filename
     *
     * @return array
     */
    protected function withoutAuthorSitemaps($entries, $filename)
    {
        if (!is_array($entries)) {
            return $entries;
        }

        $kept = [];

        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry['loc']) && $this->isAuthorSitemap($entry['loc'], $filename)) {
                continue;
            }

            $kept[] = $entry;
        }

        return $kept;
    }

    /**
     * @param mixed  $loc
     * @param string $filename
     *
     * @return bool
     */
    protected function isAuthorSitemap($loc, $filename)
    {
        if (!is_string($loc) || $loc === '') {
            return false;
        }

        $path = parse_url($loc, PHP_URL_PATH);

        if (!is_string($path)) {
            return false;
        }

        $pattern = '#^author-' . preg_quote($filename, '#') . '[0-9]*\.xml$#i';

        return (bool) preg_match($pattern, basename($path));
    }

    /**
     * The name of an All in One SEO sitemap is an option, so the index entry
     * it builds is "author-{filename}{page}.xml".
     *
     * @return string
     */
    protected function aioseoSitemapFilename()
    {
        if (!function_exists('aioseo')) {
            return self::DEFAULT_SITEMAP_FILENAME;
        }

        $aioseo = aioseo();

        if (!is_object($aioseo) || !isset($aioseo->sitemap->filename) || !is_string($aioseo->sitemap->filename)) {
            return self::DEFAULT_SITEMAP_FILENAME;
        }

        return $aioseo->sitemap->filename === ''
            ? self::DEFAULT_SITEMAP_FILENAME
            : $aioseo->sitemap->filename;
    }

    /**
     * @param array $entry
     * @param string $type
     *
     * @return array|false
     */
    public function removeRankMathAuthorEntry($entry, $type)
    {
        if ($type === 'author' || $type === 'user') {
            return false;
        }

        return $entry;
    }
}
