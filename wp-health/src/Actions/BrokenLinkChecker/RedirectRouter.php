<?php
namespace WPUmbrella\Actions\BrokenLinkChecker;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\BrokenLinkChecker\RedirectTableManager;

class RedirectRouter implements ExecuteHooks
{
    /**
     * In-memory cache to avoid multiple DB queries per request
     *
     * @var array|null
     */
    protected $redirectsCache = null;

    /**
     * URLs that should never be redirected
     */
    protected const PROTECTED_PATHS = [
        '/wp-admin',
        '/wp-login.php',
        '/wp-cron.php',
        '/wp-json',
        '/xmlrpc.php',
    ];

    public function hooks()
    {
        add_action('init', [$this, 'handleRedirect'], 12);
    }

    public function handleRedirect()
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $currentPath = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (empty($currentPath)) {
            return;
        }

        if ($this->isProtectedPath($currentPath)) {
            return;
        }

        $redirects = $this->getRedirects();

        if (empty($redirects)) {
            return;
        }

        $currentPath = rtrim($currentPath, '/');

        foreach ($redirects as $redirect) {
            if ($this->matchRedirect($redirect, $currentPath)) {
                wp_redirect($redirect->destination_url, intval($redirect->redirect_type));
                exit;
            }
        }
    }

    protected function isProtectedPath($path)
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if (strpos($path, $protected) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function matchRedirect($redirect, $currentPath)
    {
        if ($redirect->match_type === 'regex') {
            return @preg_match($redirect->source_pattern, $currentPath) === 1;
        }

        $sourcePath = rtrim($redirect->source_pattern, '/');

        return $currentPath === $sourcePath;
    }

    protected function getRedirects()
    {
        if ($this->redirectsCache !== null) {
            return $this->redirectsCache;
        }

        global $wpdb;
        $tableName = RedirectTableManager::getTableName();

        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
        );

        if ($tableExists !== $tableName) {
            $this->redirectsCache = [];
            return $this->redirectsCache;
        }

        $results = $wpdb->get_results(
            "SELECT source_pattern, destination_url, redirect_type, match_type FROM {$tableName}"
        );

        $this->redirectsCache = $results ?: [];

        return $this->redirectsCache;
    }
}
