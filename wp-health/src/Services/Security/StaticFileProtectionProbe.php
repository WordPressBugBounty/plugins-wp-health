<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class StaticFileProtectionProbe
{
    const TRANSIENT = 'wp_umbrella_static_protection_probe';
    const DIRNAME = 'wpu-static-check';
    const CONTROL_PREFIX = 'wp-umbrella-static-control-';
    const MARKER = 'wpu-static-probe-ok';

    const STATE_ENFORCED = 'enforced';
    const STATE_IGNORED = 'ignored';
    const STATE_UNKNOWN = 'unknown';

    public function getState()
    {
        return $this->read('state', self::STATE_UNKNOWN);
    }

    public function getEdgeServer()
    {
        return $this->read('server', 'unknown');
    }

    protected static $memoryCache = null;

    protected function read($key, $default)
    {
        if (is_array(self::$memoryCache) && isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key];
        }

        $cached = get_transient(self::TRANSIENT);

        if (!is_array($cached) || !isset($cached[$key])) {
            $cached = $this->refresh();
        }

        self::$memoryCache = $cached;

        return isset($cached[$key]) ? $cached[$key] : $default;
    }

    protected function refresh()
    {
        $result = $this->measure();

        $ttl = $result['state'] === self::STATE_UNKNOWN ? DAY_IN_SECONDS : 12 * HOUR_IN_SECONDS;

        set_transient(self::TRANSIENT, $result, $ttl);

        return $result;
    }

    protected function measure()
    {
        if (wp_umbrella_get_service('WebServer')->isNginx()) {
            return ['state' => self::STATE_IGNORED, 'server' => 'nginx'];
        }

        $upload = wp_upload_dir(null, false);

        if (!is_array($upload) || empty($upload['basedir']) || empty($upload['baseurl']) || !empty($upload['error'])) {
            return ['state' => self::STATE_UNKNOWN, 'server' => 'unknown'];
        }

        $fixture = $this->createFixture($upload);

        if ($fixture === null) {
            return ['state' => self::STATE_UNKNOWN, 'server' => 'unknown'];
        }

        try {
            $control = $this->request($fixture['controlUrl']);

            if ($control === null || $control['code'] !== 200 || strpos($control['body'], self::MARKER) === false) {
                return ['state' => self::STATE_UNKNOWN, 'server' => 'unknown'];
            }

            $server = $this->normalizeServer($control['server']);
            $response = $this->request($fixture['url']);

            if ($response === null) {
                return ['state' => self::STATE_UNKNOWN, 'server' => $server];
            }

            if ($response['code'] === 200 && strpos($response['body'], self::MARKER) !== false) {
                return ['state' => self::STATE_IGNORED, 'server' => $server];
            }

            if (in_array($response['code'], [401, 403, 404], true)) {
                return ['state' => self::STATE_ENFORCED, 'server' => $server];
            }

            return ['state' => self::STATE_UNKNOWN, 'server' => $server];
        } finally {
            $this->deleteFixture($fixture);
        }
    }

    protected function createFixture($upload)
    {
        $basedir = rtrim($upload['basedir'], '/\\');
        $dir = $basedir . '/' . self::DIRNAME;

        if (!wp_mkdir_p($dir)) {
            return null;
        }

        $this->sweep($basedir, $dir);

        $name = 'wp-umbrella-static-' . uniqid();
        $path = $dir . '/' . $name . '.txt';
        $controlPath = $basedir . '/' . self::CONTROL_PREFIX . uniqid() . '.txt';
        $rulesPath = $dir . '/.htaccess';

        if (file_put_contents($rulesPath, $this->rules()) === false) {
            return null;
        }

        if (file_put_contents($path, self::MARKER) === false) {
            wp_delete_file($rulesPath);

            return null;
        }

        if (file_put_contents($controlPath, self::MARKER) === false) {
            wp_delete_file($rulesPath);
            wp_delete_file($path);

            return null;
        }

        $baseUrl = rtrim($upload['baseurl'], '/\\');

        return [
            'dir' => $dir,
            'path' => $path,
            'rulesPath' => $rulesPath,
            'controlPath' => $controlPath,
            'url' => $baseUrl . '/' . self::DIRNAME . '/' . $name . '.txt',
            'controlUrl' => $baseUrl . '/' . basename($controlPath),
        ];
    }

    /**
     * Read from the hardening rather than restated, so the fixture cannot
     * measure a directory deny we no longer write.
     */
    protected function rules()
    {
        $lines = wp_umbrella_get_service('HtaccessFile')->getDenyLines();

        return implode("\n", $lines) . "\n";
    }

    protected function sweep($basedir, $dir)
    {
        $leftovers = array_merge(
            (array) glob($dir . '/wp-umbrella-static-*'),
            (array) glob($basedir . '/' . self::CONTROL_PREFIX . '*')
        );

        foreach ($leftovers as $leftover) {
            if (is_string($leftover) && is_file($leftover) && filemtime($leftover) < time() - HOUR_IN_SECONDS) {
                wp_delete_file($leftover);
            }
        }

        $rules = $dir . '/.htaccess';

        if (is_file($rules) && filemtime($rules) < time() - HOUR_IN_SECONDS) {
            wp_delete_file($rules);
        }
    }

    protected function deleteFixture($fixture)
    {
        wp_delete_file($fixture['path']);
        wp_delete_file($fixture['controlPath']);
        wp_delete_file($fixture['rulesPath']);
        @rmdir($fixture['dir']);
    }

    protected function request($url)
    {
        $url = add_query_arg('wpu_probe', uniqid(), $url);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => wp_umbrella_should_verify_ssl(),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return [
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
            'server' => (string) wp_remote_retrieve_header($response, 'server'),
        ];
    }

    protected function normalizeServer($header)
    {
        $header = strtolower($header);

        if ($header === '') {
            return 'unknown';
        }

        foreach (['litespeed', 'nginx', 'apache', 'openresty', 'cloudflare'] as $needle) {
            if (strpos($header, $needle) !== false) {
                return $needle === 'openresty' ? 'nginx' : $needle;
            }
        }

        return 'unknown';
    }
}
