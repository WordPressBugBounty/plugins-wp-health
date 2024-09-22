<?php
namespace WPUmbrella\Services;

class RequestSettings
{
    protected $updateCallResponse;
    protected $updateCallBody;

    public function getWPEngineCookieValue()
    {
        return hash('sha256', 'wpe_auth_salty_dog|' . WPE_APIKEY);
    }

    /**
     * In WP Engine hosting only requests from logged in users with auth cookies are given filesystem
     *  write access.
     */
    public function setCookies($user)
    {
        $user_id = (int) $user->ID;

        $cookies = [];
        $secure = is_ssl();
        $secure = apply_filters('secure_auth_cookie', $secure, $user_id);

        if ($secure) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme = 'auth';
        }

        $expiration = time() + (DAY_IN_SECONDS * 14);

        $cookies[$auth_cookie_name] = wp_generate_auth_cookie($user_id, $expiration, $scheme);
        $cookies[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');
        $this->preventWPEngine();
    }

    public function preventWPEngine()
    {
        if (defined('WPE_APIKEY')) {
            $cookieValue = $this->getWPEngineCookieValue();
            setcookie('wpe-auth', $cookieValue, 0, '/', '', force_ssl_admin(), true);
        }
    }

    public function setupAdminConstants()
    {
        $wpContext = wp_umbrella_get_service('WordPressContext');

        $wpContext->setConstant('WP_UMBRELLA_IS_ADMIN', true);
        $wpContext->setConstant('WP_ADMIN', true);

        if ($wpContext->isMultiSite()) {
            $wpContext->setConstant('WP_NETWORK_ADMIN', true);
        } else {
            $wpContext->setConstant('WP_NETWORK_ADMIN', false);
        }

        $wpContext->setConstant('WP_USER_ADMIN', false);
        $wpContext->setConstant('WP_BLOG_ADMIN', true);
    }

    public function setupAdmin()
    {
        if (!function_exists('get_current_screen')) {
            include_once ABSPATH . '/wp-admin/includes/class-wp-screen.php';
            include_once ABSPATH . '/wp-admin/includes/screen.php';
        }

        if (!function_exists('wp_set_current_user')) {
            include_once ABSPATH . '/wp-includes/pluggable.php';
        }

        $user = wp_umbrella_get_service('UsersProvider')->getUserAdminCanBy();
        if (!$user) {
            return false;
        }

        // Authenticated user
        wp_cookie_constants();
        wp_set_current_user((int) $user->ID, $user->user_login);
        $this->setCookies($user);
    }

    protected function preventOxygen()
    {
        try {
            if (!class_exists('OxygenMainPluginUpdater')) {
                return;
            }
            global $oxygen_updater;

            if (!$oxygen_updater) {
                $oxygen_updater = new \OxygenMainPluginUpdater([
                    'prefix' => 'oxygen_',
                    'plugin_name' => 'Oxygen',
                    'priority' => 5
                ]);
            }

            if ($oxygen_updater->edd_updater === null) {
                $oxygen_updater->init();
            }
        } catch (\Exception $e) {
            // Do nothing
        }
    }

    public function buildRequestSettings($data = [])
    {
        $this->preventOxygen();
        $this->simulateAdminEnvironment($data);

        // Master should never get redirected by the worker, since it expects worker response.
        add_filter('wp_redirect', [$this, 'disableRedirect']);

        wp_umbrella_get_service('WordPressContext')->set('_wp_using_ext_object_cache', false);
    }

    protected function simulateAdminEnvironment($data)
    {
        $context = wp_umbrella_get_service('WordPressContext');

        $_SERVER['PHP_SELF'] = '/wp-admin/' . (isset($data['wp_page']) && !empty($data['wp_page']) ? $data['wp_page'] : 'index.php');
        $_COOKIE['redirect_count'] = '10'; // hack for the WordPress HTTPS plugin, so it doesn't redirect us

        if (defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN) {
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['SERVER_PORT'] = '443';
        }

        $context->setConstant('WP_ADMIN', true);
    }

    public function adminLoaded()
    {
        try {
            $GLOBALS['hook_suffix'] = '';
            if (class_exists('WP_Screen')) {
                \WP_Screen::get('')->set_current_screen();
            }
        } catch (\Exception $e) {
            // No black magic
        }

        $context = wp_umbrella_get_service('WordPressContext');

        add_filter('http_response', [$this, 'captureCacheUpdateCall'], WP_UMBRELLA_MAX_PRIORITY_HOOK, 3);
        add_filter('pre_http_request', [$this, 'interceptCacheUpdateCall'], WP_UMBRELLA_MAX_PRIORITY_HOOK, 3);
        require_once wp_umbrella_get_service('WordPressContext')->getConstant('ABSPATH') . 'wp-admin/includes/admin.php';

        // do_action('admin_init');
        global $wp_current_filter;
        $wp_current_filter[] = 'load-update-core.php';

        if (function_exists('wp_clean_update_cache') && apply_filters('wp_umbrella_clean_cache_on_request', false)) {
            wp_clean_update_cache();
        }

        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }

        if (function_exists('wp_update_themes')) {
            wp_update_themes();
        }

        array_pop($wp_current_filter);

        set_current_screen();
        do_action('load-update-core.php');

        if (function_exists('wp_version_check')) {
            wp_version_check();
            wp_version_check([], true);
        }
    }

    public function triggerAdminInit()
    {
        wp_remote_post(
            add_query_arg(
                [
                    'x-umbrella-load-admin' => 1,
                ],
                admin_url('admin-ajax.php')
            ),
            [
                'timeout' => 30,
                'blocking' => false,
                'sslverify' => false,
				'user-agent' => 'WPUmbrella',
                'body' => [
                    'action' => 'wp_umbrella_snapshot_data',
                    'nonce' => wp_create_nonce('wp_umbrella_snapshot_data'),
                    'api_key' => wp_umbrella_get_api_key(),
                ]
            ]
        );
    }

    /**
     * @internal
     */
    public function disableRedirect()
    {
        return false;
    }

    public function captureCacheUpdateCall($response, $args, $url)
    {
        if ($url !== 'https://api.wordpress.org/plugins/update-check/1.1/') {
            return $response;
        }

        $this->updateCallResponse = $response;
        $this->updateCallBody = $args['body'];
        return $response;
    }

    public function interceptCacheUpdateCall($response, $args, $url)
    {
        if ($url !== 'https://api.wordpress.org/plugins/update-check/1.1/') {
            return $response;
        }
        if ($this->updateCallResponse === null) {
            return $response;
        }
        if ($this->updateCallBody !== http_build_query($args['body'])) {
            return $response;
        }
        return $this->updateCallResponse;
    }
}
