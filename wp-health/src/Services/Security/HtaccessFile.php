<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessFile
{
    const MARKER = 'WP Umbrella';

    public function getPath()
    {
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $home = function_exists('get_home_path') ? get_home_path() : ABSPATH;

        return rtrim($home, '/\\') . '/.htaccess';
    }

    public function exists()
    {
        return file_exists($this->getPath());
    }

    public function isWritable()
    {
        $path = $this->getPath();

        return file_exists($path) && is_writable($path);
    }

    public function getContents()
    {
        $path = $this->getPath();

        if (!file_exists($path) || !is_readable($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }

    public function hasUmbrellaBlock()
    {
        $contents = $this->getContents();

        return $contents !== '' && strpos($contents, '# BEGIN ' . self::MARKER) !== false;
    }

    public function writeUmbrellaBlock()
    {
        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            return ['status' => 'not_applicable', 'reason' => 'no_server_context'];
        }

        if ($this->isNginx()) {
            return ['status' => 'not_applicable', 'reason' => 'nginx'];
        }

        $path = $this->getPath();

        if (file_exists($path) && !is_writable($path)) {
            return ['status' => 'error', 'reason' => 'not_writable'];
        }

        if (file_exists($path) === false) {
            $dir = dirname($path);
            if (!is_writable($dir)) {
                return ['status' => 'error', 'reason' => 'not_writable'];
            }
        }

        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        $snapshot = file_exists($path) ? file_get_contents($path) : null;

        $lines = [
            '<IfModule mod_rewrite.c>',
            'RewriteRule ^wp-content/uploads/.*\.php$ - [F,L]',
            '</IfModule>',
        ];

        $written = insert_with_markers($path, self::MARKER, $lines);

        if (!$written) {
            return ['status' => 'error', 'reason' => 'write_failed'];
        }

        try {
            $selfCheck = $this->selfCheck();
        } catch (\Throwable $error) {
            $this->restore($path, $snapshot);

            return ['status' => 'error', 'reason' => 'self_check_failed'];
        }

        if ($selfCheck !== true) {
            $this->restore($path, $snapshot);

            return ['status' => 'error', 'reason' => 'self_check_failed'];
        }

        return ['status' => 'ok'];
    }

    protected function selfCheck()
    {
        $upload = wp_upload_dir();

        if (!is_array($upload) || empty($upload['basedir']) || empty($upload['baseurl']) || !empty($upload['error'])) {
            return false;
        }

        $canaryPath = rtrim($upload['basedir'], '/\\') . '/wp-umbrella-canary.php';
        $canaryUrl = rtrim($upload['baseurl'], '/\\') . '/wp-umbrella-canary.php';

        $created = file_put_contents($canaryPath, "<?php http_response_code(200); echo 'wp-umbrella-canary';");

        if ($created === false) {
            return false;
        }

        try {
            return $this->probe($canaryUrl, 403) && $this->probe(home_url('/'), 200);
        } finally {
            wp_delete_file($canaryPath);
        }
    }

    protected function probe($url, $expected)
    {
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return (int) wp_remote_retrieve_response_code($response) === $expected;
    }

    protected function restore($path, $snapshot)
    {
        if ($snapshot === null) {
            if (file_exists($path)) {
                wp_delete_file($path);
            }

            return;
        }

        file_put_contents($path, $snapshot);
    }

    protected function isNginx()
    {
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';

        if (strpos($software, 'apache') !== false || strpos($software, 'litespeed') !== false) {
            return false;
        }

        return strpos($software, 'nginx') !== false;
    }

    public function cleanUmbrellaBlock()
    {
        $path = $this->getPath();

        if (!file_exists($path)) {
            return ['status' => 'noop', 'reason' => 'no_file'];
        }

        if (!$this->hasUmbrellaBlock()) {
            return ['status' => 'noop', 'reason' => 'no_block'];
        }

        if (!is_writable($path)) {
            return ['status' => 'error', 'reason' => 'not_writable'];
        }

        $begin = '# BEGIN ' . self::MARKER;
        $end = '# END ' . self::MARKER;

        $lines = preg_split('/\r\n|\r|\n/', $this->getContents());
        $result = [];
        $inside = false;

        foreach ($lines as $line) {
            if (!$inside && trim($line) === $begin) {
                $inside = true;
                continue;
            }

            if ($inside) {
                if (trim($line) === $end) {
                    $inside = false;
                }
                continue;
            }

            $result[] = $line;
        }

        $output = rtrim(implode("\n", $result));
        $output = $output === '' ? '' : $output . "\n";

        $written = file_put_contents($path, $output);

        return $written !== false ? ['status' => 'ok'] : ['status' => 'error', 'reason' => 'write_failed'];
    }
}
