<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessFile
{
    const MARKER = 'WP Umbrella';
    const BLOCK_VERSION = 2;
    const SANDBOX_DIRNAME = 'wpu-htcheck';

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

    public function getUmbrellaBlockLines()
    {
        $extensions = '(php[0-9]?|phtml|sh)';

        $lines = [
            '# Version: ' . self::BLOCK_VERSION,
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteRule ^wp-content/uploads/.*\.' . $extensions . '$ - [F,L]',
            'RewriteRule ^wp-content/upgrade/.*\.' . $extensions . '$ - [F,L]',
        ];

        if (!is_multisite()) {
            $lines[] = 'RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]';
        }

        $lines[] = 'RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]';
        $lines[] = 'RewriteRule ^wp-includes/theme-compat/ - [F,L]';
        $lines[] = 'RewriteRule (^|/)\.(git|svn|hg)(/|$) - [F,L]';
        $lines[] = '</IfModule>';
        $lines[] = 'Options -Indexes';

        $denied = [
            '<IfModule mod_authz_core.c>',
            'Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            'Order allow,deny',
            'Deny from all',
            '</IfModule>',
        ];

        $lines[] = '<FilesMatch "^(wp-config\.php|\.env|\.user\.ini|debug\.log|error_log|readme\.html|license\.txt|composer\.json|composer\.lock|package\.json)$">';
        $lines = array_merge($lines, $denied);
        $lines[] = '</FilesMatch>';

        $lines[] = '<FilesMatch "(\.(sql|sql\.gz|bak|old|swp)|~)$">';
        $lines = array_merge($lines, $denied);
        $lines[] = '</FilesMatch>';

        return $lines;
    }

    public function getLegacyBlockLines()
    {
        return [
            '<IfModule mod_rewrite.c>',
            'RewriteRule ^wp-content/uploads/.*\.php$ - [F,L]',
            '</IfModule>',
        ];
    }

    public function getBlockVersion()
    {
        $inner = $this->getUmbrellaBlockInner();

        if ($inner === null) {
            return null;
        }

        if (preg_match('/^#\s*Version:\s*(\d+)/mi', $inner, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    public function isUmbrellaBlockCanonical()
    {
        $inner = $this->getUmbrellaBlockInner();

        if ($inner === null) {
            return false;
        }

        $version = $this->getBlockVersion();

        if ($version === 1) {
            $canonical = $this->getLegacyBlockLines();
        } elseif ($version === self::BLOCK_VERSION) {
            $canonical = $this->getUmbrellaBlockLines();
        } else {
            return false;
        }

        return $this->normalizeLines($inner) === $this->normalizeLines(implode("\n", $canonical));
    }

    protected function getUmbrellaBlockInner()
    {
        $contents = $this->getContents();

        if (preg_match('/# BEGIN ' . self::MARKER . '(.*?)# END ' . self::MARKER . '/s', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function normalizeLines($content)
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    public function writeUmbrellaBlock()
    {
        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            return ['status' => 'not_applicable', 'reason' => 'no_server_context'];
        }

        if (wp_umbrella_get_service('WebServer')->isNginx()) {
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

        $lines = $this->getUmbrellaBlockLines();

        $sandbox = $this->sandboxCheck($lines);

        if ($sandbox !== 'ok') {
            return ['status' => 'error', 'reason' => $sandbox];
        }

        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        $snapshot = file_exists($path) ? file_get_contents($path) : null;

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

    protected function sandboxCheck($lines)
    {
        $upload = wp_upload_dir();

        if (!is_array($upload) || empty($upload['basedir']) || empty($upload['baseurl']) || !empty($upload['error'])) {
            return 'verification_unavailable';
        }

        $dir = rtrim($upload['basedir'], '/\\') . '/' . self::SANDBOX_DIRNAME;
        $baseUrl = rtrim($upload['baseurl'], '/\\') . '/' . self::SANDBOX_DIRNAME;

        if (!wp_mkdir_p($dir)) {
            return 'verification_unavailable';
        }

        $canaryPath = $dir . '/canary.txt';
        $htaccessPath = $dir . '/.htaccess';

        if (file_exists($htaccessPath)) {
            wp_delete_file($htaccessPath);
        }

        try {
            if (file_put_contents($canaryPath, 'wp-umbrella-htcheck') === false) {
                return 'verification_unavailable';
            }

            if ($this->probeCode($baseUrl . '/canary.txt') !== 200) {
                return 'verification_unavailable';
            }

            if (file_put_contents($htaccessPath, implode("\n", $lines) . "\n") === false) {
                return 'verification_unavailable';
            }

            $code = $this->probeCode($baseUrl . '/canary.txt');

            if ($code === 200) {
                return 'ok';
            }

            if ($code !== null && $code >= 500) {
                return 'unsafe_directives';
            }

            return 'verification_unavailable';
        } finally {
            wp_delete_file($htaccessPath);
            wp_delete_file($canaryPath);
            @rmdir($dir);
        }
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
            $homeCode = $this->probeCode(home_url('/'));

            return $this->probeCode($canaryUrl) === 403
                && $homeCode !== null && $homeCode < 400;
        } finally {
            wp_delete_file($canaryPath);
        }
    }

    protected function probeCode($url)
    {
        $url = add_query_arg('wpu_probe', uniqid(), $url);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return (int) wp_remote_retrieve_response_code($response);
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
