<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\DirectoryFunctions;

class CleanupModule extends AbstractController
{
    public function executePost($params)
    {
        if (!isset($params['requestId'])) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_key',
            ]);
        }

        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();

        $files = [
            $source . 'cloner.php',
            $source . 'cloner_error_log',
            $source . 'cloner_attempts',
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

        // The restore writes the database outside WordPress: a persistent
        // object cache (Redis/Memcached drop-in) keeps serving pre-restore
        // rows until flushed, making a successful restore look like a no-op.
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return $this->returnResponse([
            'success' => true,
            'code' => 'success',
        ]);
    }
}
