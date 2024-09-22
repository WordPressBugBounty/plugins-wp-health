<?php
namespace WPUmbrella\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Directory
{
    public static function joinPaths()
    {
        $paths = [];

        foreach (func_get_args() as $arg) {
            if ($arg !== '') {
                $paths[] = $arg;
            }
        }

        return preg_replace('#/+#', '/', join('/', $paths));
    }
}
