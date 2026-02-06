<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\DirectoryFunctions;

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
            $source . 'umb_checksum',
            $source . 'wp-content' . DIRECTORY_SEPARATOR . 'umb_database',
        ];

        foreach ($directories as $directory) {
            DirectoryFunctions::destroyDir($directory);
        }

        return $this->returnResponse([
            'success' => true,
            'code' => 'success',
        ]);
    }
}
