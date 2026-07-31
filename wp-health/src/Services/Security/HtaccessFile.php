<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessFile
{
    const MARKER = 'WP Umbrella';
    const BLOCK_VERSION = 2;
    const UPLOADS_BLOCK_VERSION = 1;
    const SANDBOX_DIRNAME = 'wpu-htcheck';
    const CANARY_DIRNAME = 'wpu-canary';
    const UPLOADS_PROBE_TRANSIENT = 'wp_umbrella_uploads_php_probe';
    const CANARY_OUTPUT = 'wpu-canary-ok';

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

    public function getUploadsPath()
    {
        $upload = wp_upload_dir(null, false);

        if (!is_array($upload) || empty($upload['basedir']) || !empty($upload['error'])) {
            return null;
        }

        return rtrim($upload['basedir'], '/\\') . '/.htaccess';
    }

    public function hasUploadsBlock()
    {
        $path = $this->getUploadsPath();

        if ($path === null || !file_exists($path) || !is_readable($path)) {
            return false;
        }

        $contents = file_get_contents($path);

        return is_string($contents) && strpos($contents, '# BEGIN ' . self::MARKER) !== false;
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

        $denied = $this->getDenyLines();

        $lines[] = '<FilesMatch "^(wp-config\.php|\.env|\.user\.ini|debug\.log|error_log|readme\.html|license\.txt|composer\.json|composer\.lock|package\.json)$">';
        $lines = array_merge($lines, $denied);
        $lines[] = '</FilesMatch>';

        $lines[] = '<FilesMatch "(\.(sql|sql\.gz|bak|old|swp)|~)$">';
        $lines = array_merge($lines, $denied);
        $lines[] = '</FilesMatch>';

        return $lines;
    }

    protected function getDenyLines()
    {
        return [
            '<IfModule mod_authz_core.c>',
            'Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            'Order allow,deny',
            'Deny from all',
            '</IfModule>',
        ];
    }

    public function getUploadsBlockLines()
    {
        $lines = ['# Version: ' . self::UPLOADS_BLOCK_VERSION];

        $lines[] = '<FilesMatch "(?i)\.(php[0-9]?|pht|phtm|phtml|phps|phar|sh)(\.|$)">';
        $lines = array_merge($lines, $this->getDenyLines());
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

        $uploadsBlockExisted = $this->hasUploadsBlock();
        $uploads = $this->writeUploadsBlock(false);

        try {
            $selfCheck = $this->selfCheck();
        } catch (\Throwable $error) {
            $selfCheck = false;
        }

        if ($selfCheck !== true) {
            $this->restore($path, $snapshot);

            if (!$uploadsBlockExisted && isset($uploads['status']) && $uploads['status'] === 'ok') {
                $this->cleanUploadsBlock();
            }

            return ['status' => 'error', 'reason' => 'self_check_failed'];
        }

        return ['status' => 'ok', 'uploads' => $uploads];
    }

    public function writeUploadsBlock($verifyDirectives = true)
    {
        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            return ['status' => 'not_applicable', 'reason' => 'no_server_context'];
        }

        if (wp_umbrella_get_service('WebServer')->isNginx()) {
            return ['status' => 'not_applicable', 'reason' => 'nginx'];
        }

        $path = $this->getUploadsPath();

        if ($path === null) {
            return ['status' => 'error', 'reason' => 'no_uploads_dir'];
        }

        if (file_exists($path)) {
            if (!is_writable($path)) {
                return ['status' => 'error', 'reason' => 'not_writable'];
            }
        } elseif (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
            return ['status' => 'error', 'reason' => 'not_writable'];
        }

        $lines = $this->getUploadsBlockLines();

        if ($verifyDirectives) {
            $sandbox = $this->sandboxCheck($lines);

            if ($sandbox !== 'ok') {
                return ['status' => 'error', 'reason' => $sandbox];
            }
        }

        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        $written = insert_with_markers($path, self::MARKER, $lines);

        return $written ? ['status' => 'ok'] : ['status' => 'error', 'reason' => 'write_failed'];
    }

    public function cleanUploadsBlock()
    {
        $path = $this->getUploadsPath();

        if ($path === null || !file_exists($path)) {
            return ['status' => 'noop', 'reason' => 'no_file'];
        }

        if (!is_writable($path)) {
            return ['status' => 'error', 'reason' => 'not_writable'];
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || strpos($contents, '# BEGIN ' . self::MARKER) === false) {
            return ['status' => 'noop', 'reason' => 'no_block'];
        }

        $written = file_put_contents($path, $this->stripBlock($contents));

        return $written !== false ? ['status' => 'ok'] : ['status' => 'error', 'reason' => 'write_failed'];
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

        $canary = $this->createCanary($upload);

        if ($canary === null) {
            return false;
        }

        try {
            $homeCode = $this->probeCode(home_url('/'));

            return $this->probeCode($canary['url']) === 403
                && $homeCode !== null && $homeCode < 400;
        } finally {
            $this->deleteCanary($canary);
        }
    }

    /**
     * @return string blocked|executed|unknown
     */
    public function probeUploadsPhpExecution()
    {
        $signature = $this->rulesSignature();
        $cached = get_transient(self::UPLOADS_PROBE_TRANSIENT);

        if (is_array($cached) && isset($cached['signature'], $cached['state']) && $cached['signature'] === $signature) {
            return $cached['state'];
        }

        $state = $this->measureUploadsPhpExecution();

        // A loopback that cannot answer is a property of the host, not a transient
        // condition, so retrying it often only costs the customer a PHP worker.
        $ttl = $state === 'unknown' ? DAY_IN_SECONDS : 12 * HOUR_IN_SECONDS;

        set_transient(self::UPLOADS_PROBE_TRANSIENT, ['signature' => $signature, 'state' => $state], $ttl);

        return $state;
    }

    protected function rulesSignature()
    {
        $uploadsPath = $this->getUploadsPath();
        $uploads = '';

        if ($uploadsPath !== null && file_exists($uploadsPath) && is_readable($uploadsPath)) {
            $contents = file_get_contents($uploadsPath);
            $uploads = is_string($contents) ? $contents : '';
        }

        return md5($this->getContents() . '|' . $uploads);
    }

    protected function measureUploadsPhpExecution()
    {
        $upload = wp_upload_dir();

        if (!is_array($upload) || empty($upload['basedir']) || empty($upload['baseurl']) || !empty($upload['error'])) {
            return 'unknown';
        }

        $canary = $this->createCanary($upload);

        if ($canary === null) {
            return 'unknown';
        }

        try {
            $control = $this->probeResponse($canary['controlUrl']);

            if ($control === null || $control['code'] !== 200 || strpos($control['body'], self::CANARY_OUTPUT) === false) {
                return 'unknown';
            }

            $response = $this->probeResponse($canary['url']);

            if ($response === null) {
                return 'unknown';
            }

            return $response['code'] === 200 && strpos($response['body'], self::CANARY_OUTPUT) !== false
                ? 'executed'
                : 'blocked';
        } finally {
            $this->deleteCanary($canary);
        }
    }

    protected function createCanary($upload)
    {
        $dir = rtrim($upload['basedir'], '/\\') . '/' . self::CANARY_DIRNAME;

        if (!wp_mkdir_p($dir)) {
            return null;
        }

        $this->sweepCanaryDirectory($dir);

        $name = 'wp-umbrella-canary-' . uniqid();
        $path = $dir . '/' . $name . '.php';
        $controlPath = $dir . '/' . $name . '.txt';

        $created = file_put_contents($path, "<?php echo 'wpu' . '-canary-' . 'ok';");

        if ($created === false) {
            return null;
        }

        if (file_put_contents($controlPath, self::CANARY_OUTPUT) === false) {
            wp_delete_file($path);

            return null;
        }

        $baseUrl = rtrim($upload['baseurl'], '/\\') . '/' . self::CANARY_DIRNAME;

        return [
            'dir' => $dir,
            'path' => $path,
            'controlPath' => $controlPath,
            'url' => $baseUrl . '/' . $name . '.php',
            'controlUrl' => $baseUrl . '/' . $name . '.txt',
        ];
    }

    /**
     * Cleanup runs in a finally block, which a fatal or a timeout skips.
     */
    protected function sweepCanaryDirectory($dir)
    {
        $leftovers = glob($dir . '/wp-umbrella-canary-*');

        if (!is_array($leftovers)) {
            return;
        }

        foreach ($leftovers as $leftover) {
            if (is_file($leftover) && filemtime($leftover) < time() - HOUR_IN_SECONDS) {
                wp_delete_file($leftover);
            }
        }
    }

    protected function deleteCanary($canary)
    {
        wp_delete_file($canary['path']);
        wp_delete_file($canary['controlPath']);
        @rmdir($canary['dir']);
    }

    protected function probeCode($url)
    {
        $response = $this->probeResponse($url);

        return $response === null ? null : $response['code'];
    }

    protected function probeResponse($url)
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

        return [
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
        ];
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
        $result = $this->cleanRootBlock();

        if ($result['status'] === 'error') {
            return $result;
        }

        $result['uploads'] = $this->cleanUploadsBlock();

        return $result;
    }

    protected function cleanRootBlock()
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

        $written = file_put_contents($path, $this->stripBlock($this->getContents()));

        return $written !== false ? ['status' => 'ok'] : ['status' => 'error', 'reason' => 'write_failed'];
    }

    protected function stripBlock($contents)
    {
        $begin = '# BEGIN ' . self::MARKER;
        $end = '# END ' . self::MARKER;

        $lines = preg_split('/\r\n|\r|\n/', $contents);
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

        return $output === '' ? '' : $output . "\n";
    }
}
