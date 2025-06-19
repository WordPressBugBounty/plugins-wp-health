<?php
namespace WPUmbrella\Services;

abstract class DirectoryFunctions
{
    public static function destroyDir($dir)
    {
        try {
            if (!is_dir($dir) || is_link($dir)) {
                if (file_exists($dir)) {
                    return unlink($dir);
                }
            }

            $data = [];

            if (file_exists($dir)) {
                $data = scandir($dir);
            }

            if (!is_array($data) && file_exists($dir)) {
                return rmdir($dir);
            }

            foreach ($data as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (!self::destroyDir($dir . DIRECTORY_SEPARATOR . $file)) {
                    chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                    if (!self::destroyDir($dir . DIRECTORY_SEPARATOR . $file)) {
                        return false;
                    }
                };
            }

            if (file_exists($dir)) {
                return rmdir($dir);
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
