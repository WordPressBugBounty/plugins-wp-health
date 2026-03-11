<?php
namespace WPUmbrella\Actions\BrokenLinkChecker;

use WPUmbrella\Core\Hooks\ExecuteHooksFrontend;
use WPUmbrella\Services\BrokenLinkChecker\LinkTableManager;

class CollectLinks implements ExecuteHooksFrontend
{
    public function hooks()
    {
        if (!get_option('wp_umbrella_broken_link_checker_enabled')) {
            return;
        }

        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if ($this->isRestRequest()) {
            return;
        }

        if (is_admin() || $this->isLoginPage()) {
            return;
        }

        if ($this->isPageBuilderPreview()) {
            return;
        }

        if ($this->isBot()) {
            return;
        }

        add_action('init', [$this, 'startBuffer'], 10);
    }

    public function startBuffer()
    {
        LinkTableManager::ensureTableExists();

        $currentUrl = $this->getCurrentUrl();

        $repository = wp_umbrella_get_service('LinkRepository');

        if (!$repository->shouldScanPage($currentUrl)) {
            return;
        }

        $isWpEngine = apply_filters('wp_umbrella_blc_is_wp_engine', false);

        if ($isWpEngine) {
            add_filter('final_output', [$this, 'processBuffer'], 999);
        } else {
            ob_start([$this, 'processBuffer']);
        }
    }

    public function processBuffer($html)
    {
        if (!is_string($html) || empty($html)) {
            return $html;
        }

        if (strpos($html, '</html>') === false) {
            return $html;
        }

        try {
            $currentUrl = $this->getCurrentUrl();

            if (strpos($currentUrl, '/wp-content/uploads/') !== false) {
                return $html;
            }

            $collector = wp_umbrella_get_service('LinkCollector');
            $repository = wp_umbrella_get_service('LinkRepository');

            $links = $collector->extractLinks($html, $currentUrl);
            $repository->insertLinks($currentUrl, $links);

            $unsentThreshold = (defined('WP_UMBRELLA_DEBUG') && WP_UMBRELLA_DEBUG) ? 1 : 50;
            if ($repository->countUnsent() >= $unsentThreshold) {
                if (function_exists('as_next_scheduled_action') && as_next_scheduled_action('action_wp_umbrella_send_links') === false) {
                    as_schedule_single_action(time(), 'action_wp_umbrella_send_links', [], 'umbrella_links');
                }
            }
        } catch (\Exception $e) {
            // Never break the page rendering
        }

        return $html;
    }

    protected function getCurrentUrl()
    {
        $protocol = is_ssl() ? 'https' : 'http';
        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $uri;
    }


    /**
     * Comprehensive REST detection (same approach as Weglot's wg_is_rest).
     */
    protected function isRestRequest()
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (isset($_GET['rest_route'])) {
            return true;
        }

        $restPrefix = rest_get_url_prefix();
        $restUrl = wp_parse_url(site_url($restPrefix));
        $currentUrl = wp_parse_url(add_query_arg([]));

        if (isset($currentUrl['path'], $restUrl['path'])) {
            return strpos($currentUrl['path'], $restUrl['path']) === 0;
        }

        return false;
    }

    protected function isLoginPage()
    {
        return isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php';
    }

    protected function isPageBuilderPreview()
    {
        // Elementor
        if (!empty($_GET['elementor-preview'])) {
            return true;
        }

        // Breakdance
        if (!empty($_GET['_breakdance_doing_ajax'])) {
            return true;
        }

        // Divi
        if (!empty($_GET['et_fb'])) {
            return true;
        }

        // Beaver Builder
        if (!empty($_GET['fl_builder'])) {
            return true;
        }

        // WP Bakery
        if (!empty($_GET['vc_editable'])) {
            return true;
        }

        // Oxygen
        if (!empty($_GET['ct_builder'])) {
            return true;
        }

        // Bricks
        if (!empty($_GET['bricks'])) {
            return true;
        }

        return false;
    }

    protected function isBot()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return true;
        }

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'googlebot', 'bingbot', 'yandex', 'baidu',
        ];

        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

        foreach ($botPatterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
