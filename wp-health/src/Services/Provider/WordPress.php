<?php
namespace WPUmbrella\Services\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use Morphism\Morphism;

class WordPress
{
    const NAME_SERVICE = 'WordPressProvider';

    public function getMemoryLimitBytes()
    {
        try {
            $value = @ini_get('memory_limit');
            $value = trim($value);
            $last = strtolower($value[strlen($value) - 1]);
            $value = (int) substr($value, 0, -1);
        } catch (\Exception $e) {
            return 256 * 1024 * 1024;
        }

        if (!$value || $value <= 0 || $value === '-1') {
            return 256 * 1024 * 1024;
        }

        if ($last == 'g') {
            $value *= 1024 * 1024 * 1024;
        } elseif ($last == 'm') {
            $value *= 1024 * 1024;
        } elseif ($last == 'k') {
            $value *= 1024;
        }

        return $value;
    }

    /**
     * @return array
     */
    public function getPhpExtensions()
    {
        $modules = [
            'curl' => [
                'function' => 'curl_version',
                'key' => 'curl',
                'is_installed' => true,
            ],
            'dom' => [
                'class' => 'DOMNode',
                'key' => 'dom',
                'is_installed' => true,
            ],
            'exif' => [
                'function' => 'exif_read_data',
                'key' => 'exif',
                'is_installed' => true,
            ],
            'fileinfo' => [
                'function' => 'finfo_file',
                'key' => 'fileinfo',
                'is_installed' => true,
            ],
            'hash' => [
                'function' => 'hash',
                'key' => 'hash',
                'is_installed' => true,
            ],
            'json' => [
                'function' => 'json_last_error',
                'key' => 'json',
                'is_installed' => true,
            ],
            'mbstring' => [
                'function' => 'mb_check_encoding',
                'key' => 'mbstring',
                'is_installed' => true,
            ],
            'mysqli' => [
                'function' => 'mysqli_connect',
                'key' => 'mysqli',
                'is_installed' => true,
            ],
            'libsodium' => [
                'constant' => 'SODIUM_LIBRARY_VERSION',
                'key' => 'libsodium',
                'is_installed' => true,
                'php_bundled_version' => '7.2.0',
            ],
            'openssl' => [
                'function' => 'openssl_encrypt',
                'key' => 'openssl',
                'is_installed' => true,
            ],
            'pcre' => [
                'function' => 'preg_match',
                'key' => 'pcre',
                'is_installed' => true,
            ],
            'imagick' => [
                'extension' => 'imagick',
                'key' => 'imagick',
                'is_installed' => true,
            ],
            'mod_xml' => [
                'extension' => 'libxml',
                'key' => 'mod_xml',
                'is_installed' => true,
            ],
            'zip' => [
                'class' => 'ZipArchive',
                'key' => 'zip',
                'is_installed' => true,
            ],
            'filter' => [
                'function' => 'filter_list',
                'key' => 'filter',
                'is_installed' => true,
            ],
            'gd' => [
                'extension' => 'gd',
                'key' => 'gd',
                'is_installed' => true,
                'fallback_for' => 'imagick',
            ],
            'iconv' => [
                'function' => 'iconv',
                'key' => 'iconv',
                'is_installed' => true,
            ],
            'mcrypt' => [
                'extension' => 'mcrypt',
                'key' => 'mcrypt',
                'is_installed' => true,
                'fallback_for' => 'libsodium',
            ],
            'simplexml' => [
                'extension' => 'simplexml',
                'key' => 'simplexml',
                'is_installed' => true,
                'fallback_for' => 'mod_xml',
            ],
            'xmlreader' => [
                'extension' => 'xmlreader',
                'key' => 'xmlreader',
                'is_installed' => true,
                'fallback_for' => 'mod_xml',
            ],
            'zlib' => [
                'extension' => 'zlib',
                'key' => 'zlib',
                'is_installed' => true,
                'fallback_for' => 'zip',
            ],
        ];

        foreach ($modules as $library => $module) {
            $extension = (isset($module['extension']) ? $module['extension'] : null);
            $function = (isset($module['function']) ? $module['function'] : null);
            $constant = (isset($module['constant']) ? $module['constant'] : null);
            $class_name = (isset($module['class']) ? $module['class'] : null);

            // If this module is a fallback for another function, check if that other function passed.
            if (isset($module['fallback_for'])) {
                continue;
            }

            if (!$this->testPhpExtensionCompatibility($extension, $function, $constant, $class_name) && (!isset($module['php_bundled_version']) || version_compare(PHP_VERSION, $module['php_bundled_version'], '<'))) {
                $modules[$library]['is_installed'] = false;
            }
        }

        return array_values($modules);
    }

    protected function testPhpExtensionCompatibility($extension = null, $function = null, $constant = null, $class = null)
    {
        // If no extension or function is passed, claim to fail testing, as we have nothing to test against.
        if (!$extension && !$function && !$constant && !$class) {
            return false;
        }

        if ($extension && !extension_loaded($extension)) {
            return false;
        }
        if ($function && !function_exists($function)) {
            return false;
        }
        if ($constant && !defined($constant)) {
            return false;
        }
        if ($class && !class_exists($class)) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function getPhpVersion()
    {
        if (!function_exists('wp_check_php_version')) {
            include_once ABSPATH . '/wp-admin/includes/misc.php';
        }

        $response = wp_check_php_version();

        if (!is_array($response)) {
            return [
                'current_version' => PHP_VERSION,
                'recommended_version' => '7.2.0',
                'is_secure' => '7.2.0',
                'is_supported' => '7.2.0',
            ];
        }

        return [
            'current_version' => PHP_VERSION,
            'recommended_version' => $response['recommended_version'],
            'is_secure' => $response['is_secure'],
            'is_supported' => $response['is_supported'],
        ];
    }

    public function isUpToDate()
    {
        wp_version_check();

        $coreUpdate = false;

        $fromApi = get_site_option('_site_transient_update_core');
        if (isset($fromApi->updates) && is_array($fromApi->updates)) {
            $coreUpdate = $fromApi->updates;
        }

        $isUpToDate = $coreUpdate && (!isset($coreUpdate[0]->response) || 'latest' == $coreUpdate[0]->response);
        $latest = $coreUpdate && isset($coreUpdate[0]) ? $coreUpdate[0] : null;
        $wp_version = $this->getWordPressVersion();

        if (!$isUpToDate) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
            $findCoreUpdate = find_core_update($wp_version, get_locale());
            $isUpToDate = is_object($findCoreUpdate) && $findCoreUpdate->current == $wp_version;
        }

        if (!$isUpToDate) {
            return [
                'is_up_to_date' => $isUpToDate,
                'latest' => $latest
            ];
        }

        $cores = wp_umbrella_get_service('CoreProvider')->getCoreVersions();
        if (!empty($cores) && isset($cores[0])) {
            $core = $cores[0];
            if (!empty($core)) {
                $isUpToDate = $core->version === $wp_version;
            }

            if (!$isUpToDate) {
                $latest = $core;
            }
        }

        return [
            'is_up_to_date' => $isUpToDate,
            'latest' => $latest
        ];
    }

    public function getConstants()
    {
        return [
            'defined_wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'defined_wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'defined_wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
        ];
    }

    public function getWordPressVersion()
    {
        $versionFile = ABSPATH . WPINC . '/version.php';

        if (!file_exists($versionFile)) {
            global $wp_version;
            // For whatever reason.
            return $wp_version;
        }

        include $versionFile;

        if (!isset($wp_version)) {
            global $wp_version;
            return $wp_version;
        }

        return $wp_version;
    }

    public function get()
    {
        // Necessary for call rest_url(); with universal request
        wp_umbrella_get_service('WordPressContext')->requireWpRewrite();
        $restUrl = rest_url();

        $data = [];
        $data['hosting'] = wp_umbrella_get_service('HostResolver')->getCurrentHost();
        $data['is_indexable'] = $this->isIndexable();
        $data['curl_is_defined'] = function_exists('curl_init');
        $data['zip_is_defined'] = class_exists('ZipArchive');
        $data['php_version'] = $this->getPhpVersion();
        $data['defined_data'] = $this->getConstants();
        $data['wordpress_up_to_date'] = $this->isUpToDate();
        $data['php_extensions'] = $this->getPhpExtensions();
        $data['is_ssl'] = wp_is_using_https();
        $data['is_multisite'] = is_multisite();
        $data['urls'] = [
            'base_url' => site_url(),
            'rest_url' => $restUrl,
            'backdoor_url' => plugins_url(),
            'admin_url' => get_admin_url(),
            'wp_umbrella_url' => WP_UMBRELLA_DIRURL
        ];
        $data['base_directory'] = wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource();

        $data['wordpress_version'] = $this->getWordPressVersion();

        $data['disk_free_space'] = null;
        try {
            if (function_exists('disk_free_space')) {
                $data['disk_free_space'] = @disk_free_space(ABSPATH);
            }
        } catch (\Exception $e) {
            // No black magic
        }

        try {
            $data['memory_limit'] = @ini_get('memory_limit');
        } catch (\Exception $e) {
            $data['memory_limit'] = null;
        }

        $schema = [
            'is_up_to_date' => 'wordpress_up_to_date.is_up_to_date',
            'wordpress_version' => 'wordpress_version',
            'hosting' => 'hosting',
            'is_ssl' => 'is_ssl',
            'is_multisite' => 'is_multisite',
            'disk_free_space' => 'disk_free_space',
            'memory_limit' => 'memory_limit',
            'urls' => 'urls',
            'php_version' => 'php_version',
            'php_extensions' => 'php_extensions',
            'is_indexable' => 'is_indexable',
            'curl_is_defined' => 'curl_is_defined',
            'zip_is_defined' => 'zip_is_defined',
            'defined_data' => 'defined_data',
            'base_directory' => 'base_directory',
            'latest' => (object) [
                'path' => 'wordpress_up_to_date.latest',
                'fn' => function ($data) {
                    if (!$data || !\is_object($data)) {
                        return false;
                    }

                    try {
                        return [
                            'download' => \property_exists($data, 'download') ? $data->download : '',
                            'locale' => \property_exists($data, 'locale') ? $data->locale : '',
                            'full' => \property_exists($data, 'packages') ? $data->packages->full : '',
                            'no_content' => \property_exists($data, 'packages') ? $data->packages->no_content : '',
                            'new_bundled' => \property_exists($data, 'packages') ? $data->packages->new_bundled : '',
                            'partial' => \property_exists($data, 'packages') ? $data->packages->partial : '',
                            'rollback' => \property_exists($data, 'packages') ? $data->packages->rollback : '',
                            'current' => \property_exists($data, 'current') ? $data->current : '',
                            'version' => \property_exists($data, 'version') ? $data->version : '',
                            'php_version' => \property_exists($data, 'php_version') ? $data->php_version : '',
                            'mysql_version' => \property_exists($data, 'mysql_version') ? $data->mysql_version : '',
                            'new_bundled' => \property_exists($data, 'new_bundled') ? $data->new_bundled : '',
                            'partial_version' => \property_exists($data, 'partial_version') ? $data->partial_version : '',
                        ];
                    } catch (\Exception $e) {
                        return [
                            'download' => '',
                            'locale' => '',
                            'full' => '',
                            'no_content' => '',
                            'new_bundled' => '',
                            'partial' => '',
                            'rollback' => '',
                            'current' => '',
                            'version' => '',
                            'php_version' => '',
                            'mysql_version' => '',
                            'new_bundled' => '',
                            'partial_version' => '',
                        ];
                    }
                },
            ],
        ];

        Morphism::setMapper('WPUmbrella\DataTransferObject\WordPressUpdate', $schema);

        return Morphism::map('WPUmbrella\DataTransferObject\WordPressUpdate', $data);
    }

    /**
     * WordPress is indexable by robot (eg. Google) ?
     *
     * @return array
     */
    public function isIndexable()
    {
        if (1 === (int) get_option('blog_public')) {
            return true;
        }

        return false;
    }

    public function getStateWordPress()
    {
        return [
            'wordpress_version' => $this->getWordPressVersion(),
            'php_version' => PHP_VERSION
        ];
    }
}
