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

        $configuration = wp_umbrella_get_service('BackupFinderConfiguration');

        $isRestoreCleanup = false;
        if (isset($params['cleanupRestore'])) {
            $isRestoreCleanup = filter_var($params['cleanupRestore'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($params['filename']) && is_string($params['filename'])) {
            $isRestoreCleanup = basename($params['filename']) === 'restore.php';
        }

        $names = ['cloner.php', 'cloner_error_log', 'cloner_error_log.php', 'cloner_attempts'];

        if ($isRestoreCleanup) {
            $names = array_merge(
                $names,
                ['restore.php', 'restore_error_log', 'restore_error_log.php', 'restore_attempts']
            );
        }

        foreach ($configuration->getModuleRoots() as $source) {
            foreach ($names as $name) {
                $file = $source . $name;

                if (!file_exists($file)) {
                    continue;
                }

                @unlink($file);
            }
        }

        $patterns = $isRestoreCleanup
            ? $configuration->getScratchDirectoryPatternsIncludingRestore()
            : $configuration->getScratchDirectoryPatterns();

        foreach ($patterns as $pattern) {
            foreach ((array) glob($pattern, GLOB_ONLYDIR) as $directory) {
                DirectoryFunctions::destroyDir($directory);
            }
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
