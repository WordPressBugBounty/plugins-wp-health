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
    const MAX_PHP_SCAN_LEVELS = 3;
    const MAX_PHP_SCAN_ENTRIES = 500;
    const MAX_PHP_FILES_COUNTED = 25;

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

    const SUSPENDING_MODES = [
        'elementor-safe-mode.php' => [
            'option' => 'elementor_safe_mode',
            'allowed_plugins_option' => 'elementor_safe_mode_allowed_plugins',
        ],
        'health-check-troubleshooting-mode.php' => [
            'option' => 'health-check-disable-plugin-hash',
            'allowed_plugins_option' => 'health-check-allowed-plugins',
        ],
        'troubleshooting-mode.php' => [
            'option' => 'health-check-disable-plugin-hash',
            'allowed_plugins_option' => 'health-check-allowed-plugins',
        ],
    ];

    protected $muPlugins;

    protected $dropins;

    protected $unlistedPluginDirectories;

    public function analyze()
    {
        $muPlugins = $this->collectMuPlugins();
        $dropins = $this->collectDropins();
        $directories = $this->collectUnlistedPluginDirectories();
        $missingActivePlugins = $this->collectMissingActivePlugins();
        $suspendingModes = $this->collectSuspendingModes($muPlugins);

        $unexpectedMuPlugins = 0;
        foreach ($muPlugins as $entry) {
            if (!$entry['is_umbrella']) {
                ++$unexpectedMuPlugins;
            }
        }

        return [
            'analyzer_version' => $this->analyzerVersion(),
            'mu_plugins' => $muPlugins,
            'mu_plugins_dir_exists' => defined('WPMU_PLUGIN_DIR') && is_dir(WPMU_PLUGIN_DIR),
            'mu_plugins_count' => count($muPlugins),
            'unexpected_mu_plugins_count' => $unexpectedMuPlugins,
            'dropins' => $dropins,
            'dropins_count' => count($dropins),
            'unlisted_plugin_directories' => $directories,
            'unlisted_plugin_directories_count' => count($directories),
            'missing_active_plugins' => $missingActivePlugins,
            'missing_active_plugins_count' => count($missingActivePlugins),
            'suspending_modes' => $suspendingModes,
            'suspending_modes_count' => count($suspendingModes),
            'has_findings' => $unexpectedMuPlugins > 0
                || !empty($dropins)
                || !empty($directories)
                || !empty($missingActivePlugins)
                || !empty($suspendingModes),
        ];
    }

    /**
     * @return string|null
     */
    protected function analyzerVersion()
    {
        if (!defined('WP_UMBRELLA_VERSION')) {
            return null;
        }

        return (string) WP_UMBRELLA_VERSION;
    }

    protected function collectMuPlugins()
    {
        if ($this->muPlugins === null) {
            $this->muPlugins = $this->readMuPluginEntries();
        }

        return $this->muPlugins;
    }

    protected function collectDropins()
    {
        if ($this->dropins === null) {
            $this->dropins = $this->readDropinEntries();
        }

        return $this->dropins;
    }

    protected function collectUnlistedPluginDirectories()
    {
        if ($this->unlistedPluginDirectories === null) {
            $this->unlistedPluginDirectories = $this->readUnlistedPluginDirectoryEntries();
        }

        return $this->unlistedPluginDirectories;
    }

    protected function readMuPluginEntries()
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

    protected function readDropinEntries()
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

    protected function readUnlistedPluginDirectoryEntries()
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

            $scan = $this->scanPhpFiles($path);

            if ($scan['count'] === 0) {
                continue;
            }

            $header = $this->findDirectoryHeader($path);

            $entries[] = [
                'directory' => $name,
                'is_hidden' => strpos($name, '.') === 0,
                'php_files' => $scan['count'],
                'php_files_capped' => $scan['capped'],
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

    protected function collectMissingActivePlugins()
    {
        if (!defined('WP_PLUGIN_DIR')) {
            return [];
        }

        $entries = [];

        foreach ($this->readActivePluginKeys() as $key => $isNetwork) {
            if (strpos($key, '..') !== false) {
                continue;
            }

            if (file_exists(WP_PLUGIN_DIR . '/' . $key)) {
                continue;
            }

            $parts = explode('/', $key);

            $entries[] = [
                'plugin' => $key,
                'directory' => count($parts) > 1 ? $parts[0] : null,
                'is_network' => $isNetwork,
            ];

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return bool[] Plugin file keys mapped to their network scope.
     */
    protected function readActivePluginKeys()
    {
        $keys = [];

        foreach ((array) $this->readOption('active_plugins', []) as $key) {
            if (is_string($key) && $key !== '') {
                $keys[$key] = false;
            }
        }

        if (!function_exists('is_multisite') || !is_multisite()) {
            return $keys;
        }

        foreach (array_keys((array) $this->readSiteOption('active_sitewide_plugins', [])) as $key) {
            if (is_string($key) && $key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    protected function collectSuspendingModes($muPlugins)
    {
        $modes = [];

        foreach ($muPlugins as $entry) {
            if ($entry['is_directory'] || !isset(self::SUSPENDING_MODES[$entry['file']])) {
                continue;
            }

            $mode = self::SUSPENDING_MODES[$entry['file']];

            if (!$this->isOptionSet($this->readOption($mode['option'], ''))) {
                continue;
            }

            $modes[] = [
                'loader' => $entry['file'],
                'option' => $mode['option'],
                'allowed_plugins_option' => $mode['allowed_plugins_option'],
                'allowed_plugins' => $this->readAllowedPlugins($mode['allowed_plugins_option']),
            ];
        }

        return $modes;
    }

    protected function readOption($key, $default)
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        return get_option($key, $default);
    }

    protected function readSiteOption($key, $default)
    {
        if (!function_exists('get_site_option')) {
            return $default;
        }

        return get_site_option($key, $default);
    }

    protected function isOptionSet($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $value = trim((string) $value);

        return $value !== '' && $value !== '0' && $value !== 'no';
    }

    /**
     * @return string[]
     */
    protected function readAllowedPlugins($key)
    {
        $allowed = $this->readOption($key, []);

        if (!is_array($allowed)) {
            return [];
        }

        $plugins = [];

        foreach ($allowed as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            $plugins[] = $value;

            if (count($plugins) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return $plugins;
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

    /**
     * @return array{count: int, capped: bool}
     */
    protected function scanPhpFiles($path)
    {
        $budget = self::MAX_PHP_SCAN_ENTRIES;
        $count = $this->scanPhpFilesIn($path, self::MAX_PHP_SCAN_LEVELS, $budget);

        return [
            'count' => $count,
            'capped' => $count >= self::MAX_PHP_FILES_COUNTED || $budget <= 0,
        ];
    }

    /**
     * @param int $levels Directory levels left to read, the current one included.
     * @param int $budget Remaining entries, decremented across the whole walk.
     *
     * @return int
     */
    protected function scanPhpFilesIn($path, $levels, &$budget)
    {
        $count = 0;
        $directories = [];

        foreach ($this->readDirectory($path) as $name) {
            if (--$budget < 0) {
                return $count;
            }

            $child = $path . '/' . $name;

            if (substr($name, -4) === '.php' && is_file($child)) {
                ++$count;

                if ($count >= self::MAX_PHP_FILES_COUNTED) {
                    return self::MAX_PHP_FILES_COUNTED;
                }

                continue;
            }

            if ($levels > 1 && !is_link($child) && is_dir($child)) {
                $directories[] = $child;
            }
        }

        foreach ($directories as $directory) {
            $count += $this->scanPhpFilesIn($directory, $levels - 1, $budget);

            if ($count >= self::MAX_PHP_FILES_COUNTED) {
                return self::MAX_PHP_FILES_COUNTED;
            }

            if ($budget <= 0) {
                return $count;
            }
        }

        return $count;
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
