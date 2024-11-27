<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;

class CleanupModule extends AbstractController
{
    public function executePost($params)
    {
        if (!isset($params['filename'])) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_filename',
            ]);
        }

        if (!isset($params['requestId'])) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_key',
            ]);
        }

        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();

        $files = [
            $source . $params['filename'],
            $source . 'cloner_error_log',
            $source . sprintf('%s-dictionnary.php', $params['requestId']),
            $source . sprintf('dictionnary.php', $params['requestId']),
        ];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            @unlink($file);
        }

        $directories = [
            $source . 'umb_database',
            $source . 'wp-content' . DIRECTORY_SEPARATOR . 'umb_database',
        ];

        foreach ($directories as $directory) {
            $this->destroyDir($directory);
        }

        return $this->returnResponse([
            'success' => true,
            'code' => 'success',
        ]);
    }

    protected function destroyDir($dir)
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
                if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file)) {
                    chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                    if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file)) {
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
