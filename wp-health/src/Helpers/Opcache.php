<?php
namespace WPUmbrella\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Opcache
{
    protected static $isApiAllowed = null;

    public static function isApiAllowed()
    {
        if (self::$isApiAllowed !== null) {
            return self::$isApiAllowed;
        }

        if (!function_exists('opcache_invalidate')) {
            self::$isApiAllowed = false;
            return self::$isApiAllowed;
        }

        $restrictApi = ini_get('opcache.restrict_api');
        if (empty($restrictApi)) {
            self::$isApiAllowed = true;
            return self::$isApiAllowed;
        }

        $script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
        self::$isApiAllowed = $script !== false && stripos($script, $restrictApi) === 0;

        return self::$isApiAllowed;
    }

    public static function invalidate($path, $force = true)
    {
        if (!self::isApiAllowed()) {
            return false;
        }

        return @opcache_invalidate($path, $force);
    }

    public static function reset()
    {
        if (!self::isApiAllowed() || !function_exists('opcache_reset')) {
            return false;
        }

        return @opcache_reset();
    }
}
