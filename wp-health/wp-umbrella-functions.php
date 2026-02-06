<?php

use WPUmbrella\Core\Kernel;

function wp_umbrella_init_defined_standalone()
{
    define('WP_UMBRELLA_MAX_PRIORITY_HOOK', 2147483647);
    define('WP_UMBRELLA_NAME', 'WP Umbrella');
    define('WP_UMBRELLA_SLUG', 'wp-health');
    define('WP_UMBRELLA_OPTION_GROUP', 'group-wp-health');
    define('WP_UMBRELLA_VERSION', '2.21.0');
    define('WP_UMBRELLA_GOD_HANDLER_VERSION', '1.0.1');
    define('WP_UMBRELLA_PHP_MIN', '7.4');

    define('WP_UMBRELLA_DIR', __DIR__);
    define('WP_UMBRELLA_DIR_MAIN_FILE', WP_UMBRELLA_DIR . '/wp-health.php');
    define('WP_UMBRELLA_DIR_WPU_BACKUP', WP_UMBRELLA_DIR . '/wpu-backup');
    define('WP_UMBRELLA_DIR_WPU_RESTORE', WP_UMBRELLA_DIR . '/wpu-restore');
    define('WP_UMBRELLA_DIR_WPU_BACKUP_BOX', WP_UMBRELLA_DIR_WPU_BACKUP . '/box');
    define('WP_UMBRELLA_DIR_TEMP_RESTORE', WP_UMBRELLA_DIR . '/temp-restore');
    define('WP_UMBRELLA_LANGUAGES', WP_UMBRELLA_DIR . '/languages/');
    define('WP_UMBRELLA_DIR_DIST', WP_UMBRELLA_DIR . '/dist');
    define('WP_UMBRELLA_SITE_URL', 'https://wp-umbrella.com');

    $local = ['wp-health.local', 'umbrella.local', 'umbrella-test.local', 'multisite.local', 'wp-umbrella.local', 'localhost'];
    if (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], $local, true)) {
        define('WP_UMBRELLA_NEW_API_URL', 'http://localhost:3001');
        define('WP_UMBRELLA_APP_URL', 'http://localhost:3000');
        define('WP_UMBRELLA_DEBUG', true);
    } elseif (file_exists(ABSPATH . 'wp-umbrella-config.php')) {
        require_once ABSPATH . '/wp-umbrella-config.php';
    } else {
        define('WP_UMBRELLA_NEW_API_URL', 'https://api-umb1.wp-umbrella.com');
        define('WP_UMBRELLA_APP_URL', 'https://app.wp-umbrella.com');
        define('WP_UMBRELLA_DEBUG', false);
    }
}

function wp_umbrella_init_defined()
{
    if (defined('WP_UMBRELLA_NAME')) {
        return;
    }

    wp_umbrella_init_defined_standalone();

    define('WP_UMBRELLA_BNAME', plugin_basename(__DIR__ . '/wp-health.php'));
    define('WP_UMBRELLA_DIRURL', plugin_dir_url(__FILE__));

    $dist = WP_UMBRELLA_DIRURL . 'dist';

    $pos = substr_count($dist, 'wp-content/plugins');
    if ($pos >= 2) {
        $dist = sprintf('%s/%s', untrailingslashit(site_url()), 'wp-content/plugins/wp-health/dist');
    }

    $local = ['wp-health.local', 'umbrella.local', 'umbrella-test.local', 'multisite.local', 'wp-umbrella.local', 'localhost'];
    if (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], $local, true)) {
        $dist = 'http://' . $_SERVER['HTTP_HOST'] . '/wp-content/plugins/wp-health/dist'; // Because of symlink
    }

    define('WP_UMBRELLA_URL_DIST', $dist);

    define('WP_UMBRELLA_TEMPLATES', WP_UMBRELLA_DIR . '/templates');
    define('WP_UMBRELLA_TEMPLATES_ADMIN', WP_UMBRELLA_TEMPLATES . '/admin');
    define('WP_UMBRELLA_TEMPLATES_ADMIN_NOTICES', WP_UMBRELLA_TEMPLATES_ADMIN . '/notices');
    define('WP_UMBRELLA_TEMPLATES_ADMIN_PAGES', WP_UMBRELLA_TEMPLATES_ADMIN . '/pages');
}

/**
 * Check compatibility this WP Umbrella with WordPress config.
 */
function wp_umbrella_is_compatible()
{
    // Check php version.
    if (version_compare(PHP_VERSION, WP_UMBRELLA_PHP_MIN) < 0) {
        add_action('admin_notices', 'wp_umbrella_php_min_compatibility');

        return false;
    }

    return true;
}

/**
 * Admin notices if wp_umbrella not compatible.
 */
function wp_umbrella_php_min_compatibility()
{
    if (!file_exists(WP_UMBRELLA_TEMPLATES_ADMIN_NOTICES . '/php-min.php')) {
        return;
    }

    include_once WP_UMBRELLA_TEMPLATES_ADMIN_NOTICES . '/php-min.php';
}

/**
 * Get a service.
 *
 * @param string $service
 *
 * @return object
 */
function wp_umbrella_get_service($service)
{
    return Kernel::getContainer()->get($service);
}

/**
 * Get all options.
 *
 * @return array
 */
function wp_umbrella_get_options($options = ['secure' => true])
{
    $secure = $options['secure'] ?? true;
    return wp_umbrella_get_service('Option')->getOptions([
        'secure' => $secure,
    ]);
}

/**
 * Get option.
 *
 * @param string $key
 *
 * @return any
 */
function wp_umbrella_get_option($key, $secure = true)
{
    return wp_umbrella_get_service('Option')->getOption($key, $secure);
}

/**
 * @return bool
 */
function wp_umbrella_allowed()
{
    return wp_umbrella_get_option('allowed');
}

/**
 * Get API KEY.
 *
 * @return string | false
 */
function wp_umbrella_get_api_key()
{
    return wp_umbrella_get_option('api_key', false);
}

function wp_umbrella_get_project_id()
{
    return wp_umbrella_get_option('project_id');
}

function wp_umbrella_get_secret_token()
{
    return wp_umbrella_get_option('secret_token', false);
}

function wp_umbrella_generate_random_string($length = 64)
{
    $randomCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $randomCharacters[rand(0, strlen($randomCharacters) - 1)];
    }
    return $randomString;
}

function wp_umbrella_is_new_hash()
{
    return get_option('_wp_umbrella_is_new_hash', false);
}

function wp_umbrella_init_new_hash()
{
    return update_option('_wp_umbrella_is_new_hash', true, false);
}

/**
 * From SecuPress
 */
function wp_umbrella_remove_file($file)
{
    // Is it a sym link ?
    if (is_link($file)) {
        $file = @readlink($file);
    }
    // Try to delete.
    if (file_exists($file) && !@unlink($file)) {
        try {
            // Or try to empty it.
            $handler = @fopen($file, 'w');
            $responsefWrite = @fwrite($handler, '<?php // File removed by WP Umbrella');
            @fclose($handler);
            if (!$responsefWrite) {
                // Or try to rename it.
                return @rename($file, $file . '.old');
            }
        } catch (\Exception $e) {
        }
    }
    return true;
}
