<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class DebugStatusCollector implements CollectorInterface
{
    use ErrorLogPathTrait;

    public function getId()
    {
        return 'debug_status';
    }

    public function collect()
    {
        $logPath = $this->resolveErrorLogPath();

        $fileInfo = null;
        if ($logPath && file_exists($logPath) && is_readable($logPath)) {
            $size = @filesize($logPath);
            $mtime = @filemtime($logPath);

            $fileInfo = [
                'path' => $logPath,
                'exists' => true,
                'size_bytes' => $size !== false ? $size : 0,
                'size_formatted' => $size !== false ? size_format($size) : 'unknown',
                'last_modified' => $mtime !== false ? date('Y-m-d H:i:s', $mtime) : null,
            ];
        }

        return [
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : true,
            'log_errors' => (bool) ini_get('log_errors'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting_level' => error_reporting(),
            'log_file' => $fileInfo,
        ];
    }
}
