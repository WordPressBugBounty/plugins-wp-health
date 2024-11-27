<?php
namespace WPUmbrella\Services\Restore\V2;

use Exception;
use WPUmbrella\Services\Restore\V2\RestorationDirectory;
use function file_exists;
use function sprintf;
use function unlink;
use function wp_umbrella_get_service;

class Cleanup
{
    public function handle(): array
    {
        try {
            $this->destroyDir(WP_UMBRELLA_DIR_WPU_RESTORE, [
                WP_UMBRELLA_DIR_WPU_RESTORE . '/index.php',
                WP_UMBRELLA_DIR_WPU_RESTORE . '/.htaccess'
            ]);

            $restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);
            $restorationDirectory->removeSecureFile();

            $response = [
                'success' => true,
                'data' => [
                    'message' => 'Cleanup done',
                ],
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'data' => [
                    'code' => 'cleanup_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }

        return $response;
    }

    protected function destroyDir($dir, $excludes = [])
    {
        if (!\file_exists($dir)) {
            return;
        }

        if (!is_dir($dir) || is_link($dir)) {
            if (in_array($dir, $excludes, true)) {
                return true;
            }

            return wp_umbrella_remove_file($dir);
        }
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file, $excludes)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file, $excludes)) {
                    return false;
                }
            };
        }

        return @rmdir($dir);
    }
}
