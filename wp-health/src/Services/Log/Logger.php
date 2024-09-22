<?php
namespace WPUmbrella\Services\Log;

if (!defined('ABSPATH')) {
    exit;
}

class Logger
{
    protected $levels = [
        'info',
        'error',
        'critical'
    ];

    protected function getDirectoryLogger()
    {
        return apply_filters('wp_umbrella_logger_directory', WP_CONTENT_DIR . '/wp-umbrella-logs');
    }

    public function getFilename($type)
    {
        return sprintf('%s/%s.txt', $this->getDirectoryLogger(), $type);
    }

    public function isWritableDirectoryLogger()
    {
        return is_writable(dirname($this->getDirectoryLogger()));
    }

    public function createLogger()
    {
    }

    public function deleteLogger()
    {
        $directory = $this->getDirectoryLogger();

        if (!$this->isWritableDirectoryLogger()) {
            return false;
        }

        if (!file_exists($directory)) {
            return false;
        }

        $htaccess = $this->getDirectoryLogger() . '/.htaccess';

        try {
            if (file_exists($htaccess)) {
                wp_umbrella_remove_file($htaccess);
            }
        } catch (\Exception $e) {
        }

        $index = $this->getDirectoryLogger() . '/index.php';
        try {
            if (file_exists($index)) {
                wp_umbrella_remove_file($index);
            }
        } catch (\Exception $e) {
        }

        $info = $this->getDirectoryLogger() . '/info.php';
        try {
            if (file_exists($info)) {
                wp_umbrella_remove_file($info);
            }
        } catch (\Exception $e) {
        }

        foreach ($this->levels as $level) {
            $file = $this->getFilename($level);
            try {
                if (file_exists($file)) {
                    wp_umbrella_remove_file($file);
                }
            } catch (\Exception $e) {
            }
        }

        $this->deleteDirectory();
    }

    public function getMaxFileSizeLog()
    {
        return apply_filters('wp_umbrella_get_max_file_size_log', 200); // items length array
    }

    protected function deleteDirectory()
    {
        $directory = $this->getDirectoryLogger();

        if (!$this->isWritableDirectoryLogger()) {
            return false;
        }

        try {
            if (file_exists($directory)) {
                @rmdir($directory);
            }
        } catch (\Exception $e) {
        }

        return true;
    }

    protected function getLog($type)
    {
        $content = get_option(sprintf('wp_umbrella_logs_%s', $type));

        if (!$content || empty($content)) {
            return [];
        }

        try {
            return $content;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function saveLog($message, $type = 'info')
    {
        try {
            $data = $this->getLog($type);

            if (!is_array($data) || count($data) > $this->getMaxFileSizeLog()) {
                $data = [];
            }

            $data[] = [
                'time' => \date('Y-m-d H:i:s'),
                'message' => $message
            ];

            update_option(sprintf('wp_umbrella_logs_%s', $type), $data, false);
        } catch (\Exception $e) {
            return;
        }
    }

    public function info($message)
    {
        $this->saveLog($message, 'info');
    }

    public function error($message)
    {
        $this->saveLog($message, 'error');
    }

    public function critical($message)
    {
        $this->saveLog($message, 'critical');
    }

    public function getLogs()
    {
        $logs = [];
        foreach ($this->levels as $key => $value) {
            $logs[$value] = $this->getLog($value);
        }

        return $logs;
    }
}
