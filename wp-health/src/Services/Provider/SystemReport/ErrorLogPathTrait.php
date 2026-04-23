<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

trait ErrorLogPathTrait
{
    protected function resolveErrorLogPath()
    {
        // 1. WP_DEBUG_LOG as custom path
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '1' && WP_DEBUG_LOG !== '') {
            if (file_exists(WP_DEBUG_LOG)) {
                return WP_DEBUG_LOG;
            }
        }

        // 2. PHP ini error_log
        $iniPath = ini_get('error_log');
        if (!empty($iniPath) && file_exists($iniPath)) {
            return $iniPath;
        }

        // 3. WordPress default
        $defaultPath = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }

        return null;
    }
}
