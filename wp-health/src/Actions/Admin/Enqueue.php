<?php
namespace WPUmbrella\Actions\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class Enqueue implements ExecuteHooksBackend
{
    public function hooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueCSS']);
        add_filter('admin_body_class', [$this, 'bodyClass'], 100);
    }

    public function bodyClass($classes)
    {
        if (!isset($_GET['page'])) {
            return $classes;
        }

        $pages = [
            'wp-umbrella-settings' => true,
        ];

        if (isset($pages[$_GET['page']])) {
            $classes .= ' wp-umbrella-styles ';
        }
        return $classes;
    }

    public function adminEnqueueCSS($page)
    {
        if (!in_array($page, ['settings_page_wp-umbrella-settings'], true) && false === strpos($page, 'wp-umbrella')) {
            return;
        }

        $version = WP_UMBRELLA_DEBUG ? md5(time()) : WP_UMBRELLA_VERSION;

        wp_enqueue_style('wp-umbrella-tw', WP_UMBRELLA_URL_DIST . '/style.css', [], $version);
        wp_enqueue_style('wp-umbrella-sweetalert', WP_UMBRELLA_DIRURL . 'app/styles/sweetalert.css', [], $version);
    }

    /**
     * @see admin_enqueue_scripts
     *
     * @param string $page
     */
    public function adminEnqueueScripts($page)
    {
        if (!in_array($page, ['settings_page_wp-umbrella-settings'], true) && false === strpos($page, 'wp-umbrella')) {
            return;
        }
        $version = WP_UMBRELLA_DEBUG ? md5(time()) : WP_UMBRELLA_VERSION;

        wp_register_script('wp-umbrella-sweetalert', WP_UMBRELLA_DIRURL . 'app/javascripts/sweetalert.js', [], $version, true);
        wp_enqueue_script('wp-umbrella-sweetalert');
    }
}
