<?php
namespace WPUmbrella\Actions\Hardening\AttackerIps;

if (!defined('ABSPATH')) {
    exit;
}

class FilterStorage
{
    const SUBDIR = 'wp-umbrella';

    const FILENAME = 'login-guard-filter.bin';

    const OPTION_BLOB = 'wp_umbrella_login_guard_filter_blob';

    const OPTION_FETCHED_AT = 'wp_umbrella_login_guard_filter_fetched_at';

    const TRANSIENT_BACKOFF = 'wp_umbrella_login_guard_filter_backoff';

    const BACKOFF_TTL = 900;

    const FILE_THRESHOLD = 262144;

    const INDEX_GUARD = "<?php // Silence is golden.";

    public function load()
    {
        $blob = $this->readOption();

        if ($blob === null) {
            $blob = $this->readFile();
        }

        if ($blob === null) {
            return null;
        }

        if (!BloomFilter::isValidBlob($blob)) {
            return null;
        }

        return $blob;
    }

    public function store($blob)
    {
        if (!is_string($blob) || !BloomFilter::isValidBlob($blob)) {
            return false;
        }

        if (strlen($blob) <= self::FILE_THRESHOLD) {
            return $this->storeOption($blob);
        }

        if ($this->writeFile($blob)) {
            delete_option(self::OPTION_BLOB);

            return true;
        }

        return $this->storeOption($blob);
    }

    protected function storeOption($blob)
    {
        $this->deleteFile();

        $encoded = base64_encode($blob);

        if (get_option(self::OPTION_BLOB, null) === $encoded) {
            return true;
        }

        return update_option(self::OPTION_BLOB, $encoded, false);
    }

    public function getFetchedAt()
    {
        return (int) get_option(self::OPTION_FETCHED_AT, 0);
    }

    public function markFetched()
    {
        update_option(self::OPTION_FETCHED_AT, time(), false);
    }

    public function isBackingOff()
    {
        return get_transient(self::TRANSIENT_BACKOFF) !== false;
    }

    public function markFailure()
    {
        set_transient(self::TRANSIENT_BACKOFF, 1, self::BACKOFF_TTL);
    }

    public function clear()
    {
        delete_option(self::OPTION_FETCHED_AT);
        delete_option(self::OPTION_BLOB);
        delete_transient(self::TRANSIENT_BACKOFF);
        $this->deleteFile();
    }

    protected function deleteFile()
    {
        $path = $this->filePath();

        if ($path !== null && file_exists($path)) {
            @unlink($path);
        }
    }

    protected function readFile()
    {
        $path = $this->filePath();

        if ($path === null || !file_exists($path) || !is_readable($path)) {
            return null;
        }

        $blob = @file_get_contents($path);

        return $blob === false ? null : $blob;
    }

    protected function readOption()
    {
        $encoded = get_option(self::OPTION_BLOB, null);

        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $blob = base64_decode($encoded, true);

        return $blob === false ? null : $blob;
    }

    protected function writeFile($blob)
    {
        $dir = $this->directory();

        if ($dir === null) {
            return false;
        }

        if (!wp_mkdir_p($dir)) {
            return false;
        }

        $this->ensureIndexGuard($dir);
        $this->ensureDenyGuard($dir);

        if (!wp_is_writable($dir)) {
            return false;
        }

        $path = $dir . '/' . self::FILENAME;
        $tmp = $path . '.tmp';

        if (@file_put_contents($tmp, $blob) === false) {
            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    protected function ensureIndexGuard($dir)
    {
        $index = $dir . '/index.php';

        if (!file_exists($index)) {
            @file_put_contents($index, self::INDEX_GUARD);
        }
    }

    protected function ensureDenyGuard($dir)
    {
        $htaccess = $dir . '/.htaccess';

        if (file_exists($htaccess)) {
            return;
        }

        $lines = wp_umbrella_get_service('HtaccessFile')->getDenyLines();

        @file_put_contents($htaccess, implode("\n", $lines) . "\n");
    }

    protected function directory()
    {
        $upload = wp_upload_dir();

        if (!is_array($upload) || empty($upload['basedir'])) {
            return null;
        }

        return $upload['basedir'] . '/' . self::SUBDIR;
    }

    protected function filePath()
    {
        $dir = $this->directory();

        if ($dir === null) {
            return null;
        }

        return $dir . '/' . self::FILENAME;
    }
}
