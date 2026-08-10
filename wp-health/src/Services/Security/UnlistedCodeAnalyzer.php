<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class UnlistedCodeAnalyzer
{
    const MAX_ENTRIES = 200;
    const MAX_HASHED_BYTES = 2097152;
    const MAX_HEADER_PROBES = 10;

    const UMBRELLA_MU_FILES = ['InitUmbrella.php', '_WPHealthHandlerMU.php'];

    const KNOWN_DROPINS = [
        'advanced-cache.php',
        'db.php',
        'db-error.php',
        'install.php',
        'maintenance.php',
        'object-cache.php',
        'php-error.php',
        'fatal-error-handler.php',
        'sunrise.php',
        'blog-deleted.php',
        'blog-inactive.php',
        'blog-suspended.php',
    ];

    public function analyze()
    {
        $muPlugins = $this->collectMuPlugins();
        $dropins = $this->collectDropins();
        $directories = $this->collectUnlistedPluginDirectories();

        $unexpectedMuPlugins = 0;
        foreach ($muPlugins as $entry) {
            if (!$entry['is_umbrella']) {
                ++$unexpectedMuPlugins;
            }
        }

        return [
            'mu_plugins' => $muPlugins,
            'mu_plugins_dir_exists' => defined('WPMU_PLUGIN_DIR') && is_dir(WPMU_PLUGIN_DIR),
            'mu_plugins_count' => count($muPlugins),
            'unexpected_mu_plugins_count' => $unexpectedMuPlugins,
            'dropins' => $dropins,
            'dropins_count' => count($dropins),
            'unlisted_plugin_directories' => $directories,
            'unlisted_plugin_directories_count' => count($directories),
            'has_findings' => $unexpectedMuPlugins > 0 || !empty($dropins) || !empty($directories),
        ];
    }

    protected function collectMuPlugins()
    {
        if (!defined('WPMU_PLUGIN_DIR') || !is_dir(WPMU_PLUGIN_DIR)) {
            return [];
        }

        $headers = $this->readMuPluginHeaders();
        $entries = [];

        foreach ($this->readDirectory(WPMU_PLUGIN_DIR) as $name) {
            $path = WPMU_PLUGIN_DIR . '/' . $name;

            if (is_dir($path)) {
                $entries[] = [
                    'file' => $name,
                    'is_directory' => true,
                    'is_umbrella' => false,
                    'auto_loaded' => false,
                    'name' => null,
                    'version' => null,
                    'php_files' => $this->countPhpFiles($path),
                    'size' => null,
                    'hash' => null,
                    'modified_at' => $this->modifiedAt($path),
                ];
                continue;
            }

            if (substr($name, -4) !== '.php') {
                continue;
            }

            $header = isset($headers[$name]) ? $headers[$name] : [];

            $entries[] = [
                'file' => $name,
                'is_directory' => false,
                'is_umbrella' => in_array($name, self::UMBRELLA_MU_FILES, true),
                'auto_loaded' => $name !== 'index.php',
                'name' => $this->headerValue($header, 'Name'),
                'version' => $this->headerValue($header, 'Version'),
                'php_files' => null,
                'size' => $this->size($path),
                'hash' => $this->hash($path),
                'modified_at' => $this->modifiedAt($path),
            ];

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return $entries;
    }

    protected function collectDropins()
    {
        if (!defined('WP_CONTENT_DIR') || !is_dir(WP_CONTENT_DIR)) {
            return [];
        }

        $entries = [];

        foreach ($this->readDirectory(WP_CONTENT_DIR) as $name) {
            if (substr($name, -4) !== '.php' || $name === 'index.php') {
                continue;
            }

            $path = WP_CONTENT_DIR . '/' . $name;

            if (!is_file($path)) {
                continue;
            }

            $header = $this->readPluginHeader($path);

            $entries[] = [
                'file' => $name,
                'is_known_dropin' => in_array($name, self::KNOWN_DROPINS, true),
                'name' => $this->headerValue($header, 'Name'),
                'version' => $this->headerValue($header, 'Version'),
                'size' => $this->size($path),
                'hash' => $this->hash($path),
                'modified_at' => $this->modifiedAt($path),
            ];

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return $entries;
    }

    protected function collectUnlistedPluginDirectories()
    {
        if (!defined('WP_PLUGIN_DIR') || !is_dir(WP_PLUGIN_DIR)) {
            return [];
        }

        $listed = $this->readListedPluginDirectories();
        $entries = [];

        foreach ($this->readDirectory(WP_PLUGIN_DIR) as $name) {
            $path = WP_PLUGIN_DIR . '/' . $name;

            if (!is_dir($path) || isset($listed[$name])) {
                continue;
            }

            $phpFiles = $this->countPhpFiles($path);

            if ($phpFiles === 0) {
                continue;
            }

            $header = $this->findDirectoryHeader($path);

            $entries[] = [
                'directory' => $name,
                'is_hidden' => strpos($name, '.') === 0,
                'php_files' => $phpFiles,
                'name' => $this->headerValue($header, 'Name'),
                'version' => $this->headerValue($header, 'Version'),
                'modified_at' => $this->modifiedAt($path),
            ];

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return int[] Unix timestamps.
     */
    public function getModificationTimes()
    {
        $times = [];

        foreach ($this->collectMuPlugins() as $entry) {
            if (!$entry['is_umbrella'] && $entry['modified_at'] !== null) {
                $times[] = $entry['modified_at'];
            }
        }

        foreach ($this->collectDropins() as $entry) {
            if ($entry['modified_at'] !== null) {
                $times[] = $entry['modified_at'];
            }
        }

        foreach ($this->collectUnlistedPluginDirectories() as $entry) {
            if ($entry['modified_at'] !== null) {
                $times[] = $entry['modified_at'];
            }
        }

        return $times;
    }

    protected function readListedPluginDirectories()
    {
        if (!function_exists('get_plugins')) {
            if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
                return [];
            }

            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $listed = [];

        foreach (array_keys((array) get_plugins()) as $key) {
            $parts = explode('/', $key);

            if (count($parts) > 1) {
                $listed[$parts[0]] = true;
            }
        }

        return $listed;
    }

    protected function readMuPluginHeaders()
    {
        if (!function_exists('get_mu_plugins')) {
            if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
                return [];
            }

            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!function_exists('get_mu_plugins')) {
            return [];
        }

        return (array) get_mu_plugins();
    }

    protected function readPluginHeader($path)
    {
        if (!function_exists('get_plugin_data')) {
            return [];
        }

        return (array) get_plugin_data($path, false, false);
    }

    protected function findDirectoryHeader($path)
    {
        $probes = 0;

        foreach ($this->readDirectory($path) as $name) {
            if (substr($name, -4) !== '.php') {
                continue;
            }

            $header = $this->readPluginHeader($path . '/' . $name);

            if ($this->headerValue($header, 'Name') !== null) {
                return $header;
            }

            if (++$probes >= self::MAX_HEADER_PROBES) {
                break;
            }
        }

        return [];
    }

    protected function countPhpFiles($path)
    {
        $count = 0;

        foreach ($this->readDirectory($path) as $name) {
            if (substr($name, -4) === '.php') {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return string[]
     */
    protected function readDirectory($path)
    {
        if (!is_dir($path) || !is_readable($path)) {
            return [];
        }

        $names = @scandir($path);

        if (!is_array($names)) {
            return [];
        }

        return array_values(array_diff($names, ['.', '..']));
    }

    protected function headerValue($header, $key)
    {
        if (!isset($header[$key]) || !is_string($header[$key]) || $header[$key] === '') {
            return null;
        }

        return $header[$key];
    }

    protected function size($path)
    {
        $size = @filesize($path);

        return $size === false ? null : (int) $size;
    }

    protected function hash($path)
    {
        $size = $this->size($path);

        if ($size === null || $size > self::MAX_HASHED_BYTES) {
            return null;
        }

        $hash = @md5_file($path);

        return $hash === false ? null : $hash;
    }

    protected function modifiedAt($path)
    {
        $time = @filemtime($path);

        return $time === false ? null : (int) $time;
    }
}
