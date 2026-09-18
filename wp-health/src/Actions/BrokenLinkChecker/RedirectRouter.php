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

    /** Matches the source_pattern column width. */
    protected const MAX_PATH_LENGTH = 2048;

    /** Rules evaluated per request. */
    protected const MAX_REDIRECTS = 1000;

    const MAX_REGEX_PATTERN_LENGTH = 512;

    const REGEX_BACKTRACK_LIMIT = 100000;

    const MAX_MATCHING_SECONDS = 0.1;

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

        if (empty($currentPath) || strlen($currentPath) > self::MAX_PATH_LENGTH) {
            return;
        }

        if ($this->isProtectedPath($currentPath)) {
            return;
        }

        $redirects = $this->getRedirects();

        if (empty($redirects)) {
            return;
        }

        $redirect = $this->findRedirect($redirects, rtrim($currentPath, '/'));

        if ($redirect === null) {
            return;
        }

        wp_redirect($redirect->destination_url, intval($redirect->redirect_type));
        exit;
    }

    protected function findRedirect($redirects, $currentPath)
    {
        $previousLimit = function_exists('ini_set')
            ? ini_set('pcre.backtrack_limit', (string) self::REGEX_BACKTRACK_LIMIT)
            : false;

        $deadline = microtime(true) + self::MAX_MATCHING_SECONDS;
        $match = null;

        foreach ($redirects as $redirect) {
            if ($this->matchRedirect($redirect, $currentPath)) {
                $match = $redirect;
                break;
            }

            if (microtime(true) > $deadline) {
                break;
            }
        }

        if ($previousLimit !== false) {
            ini_set('pcre.backtrack_limit', $previousLimit);
        }

        return $match;
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
        if ($redirect->match_type !== 'regex') {
            return $currentPath === rtrim($redirect->source_pattern, '/');
        }

        if (strlen($redirect->source_pattern) > self::MAX_REGEX_PATTERN_LENGTH) {
            return false;
        }

        // preg_match() returns false, not 0, when it does not run to completion.
        return @preg_match($redirect->source_pattern, $currentPath) === 1;
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
            $wpdb->prepare(
                "SELECT source_pattern, destination_url, redirect_type, match_type FROM {$tableName} LIMIT %d",
                self::MAX_REDIRECTS
            )
        );

        $this->redirectsCache = $results ?: [];

        return $this->redirectsCache;
    }
}
