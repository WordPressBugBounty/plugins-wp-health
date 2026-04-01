<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class GetSitemapUrl extends AbstractController
{
    public function executeGet($params)
    {
        $sitemapUrl = $this->detectSitemapUrl();

        return $this->returnResponse([
            'success' => true,
            'sitemapUrl' => $sitemapUrl,
        ]);
    }

    public function executePost($params)
    {
        return $this->executeGet($params);
    }

    protected function detectSitemapUrl()
    {
        $siteUrl = site_url();

        // Yoast SEO
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Sitemaps')) {
            return $siteUrl . '/sitemap_index.xml';
        }

        // RankMath
        if (class_exists('RankMath')) {
            return $siteUrl . '/sitemap_index.xml';
        }

        // SEOPress
        if (defined('SEOPRESS_VERSION')) {
            return $siteUrl . '/sitemaps.xml';
        }

        // All in One SEO
        if (defined('AIOSEO_VERSION')) {
            return $siteUrl . '/sitemap.xml';
        }

        // The SEO Framework
        if (defined('THE_SEO_FRAMEWORK_VERSION')) {
            return $siteUrl . '/sitemap.xml';
        }

        // WordPress native sitemap (5.5+)
        if (function_exists('wp_sitemaps_get_server')) {
            $server = wp_sitemaps_get_server();
            if ($server && method_exists($server, 'index_url')) {
                return $server->index_url();
            }

            return $siteUrl . '/wp-sitemap.xml';
        }

        return null;
    }
}
