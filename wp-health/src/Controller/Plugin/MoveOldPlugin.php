<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

class MoveOldPlugin extends AbstractController
{
    /**
     * We use GET method to set the backup version of the plugin because
     * too many hosts block POST or PUT requests unnecessarily.
     */
    public function executeGet($params)
    {
        // Like "hello-world/hello-world.php"
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$plugin) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        $pluginSlug = dirname($plugin);
        $trace = wp_umbrella_get_service('RequestTrace');
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;
        $hasToken = is_string($lockToken) && $lockToken !== '';
        $lock = wp_umbrella_get_service('UpdateLock');
        $pluginDirectory = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $pluginSlug;

        $trace->addTrace('restore_started', [
            'plugin' => $pluginSlug,
            'plugin_dir_exists' => is_dir($pluginDirectory),
            'main_file_exists' => file_exists($pluginDirectory . DIRECTORY_SEPARATOR . basename($plugin)),
        ]);

        if ($lock->isHeldByOther($pluginSlug, $lockToken)) {
            $trace->addTrace('update_lock_held');
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress',
                'message' => sprintf('An update is already in progress for plugin %s', $pluginSlug),
                'holder' => $lock->holderFingerprint($pluginSlug),
                'data' => '',
            ]);
        }

        if ($lock->phase($pluginSlug) === UpdateLock::PHASE_UPDATING) {
            $trace->addTrace('update_lock_self_updating');
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress_self',
                'message' => sprintf('An update is still running for plugin %s', $pluginSlug),
                'holder' => $lock->holderFingerprint($pluginSlug),
                'data' => '',
            ]);
        }

        try {
            $result = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'plugins',
                'slug' => $pluginSlug,
            ]);

            if (!empty($result['success']) && $hasToken) {
                $lock->release($pluginSlug, $lockToken);
            }

            if (empty($result['success'])) {
                $trace->addTrace('restore_failed', [
                    'code' => isset($result['code']) ? $result['code'] : 'unknown',
                    'backup_is_dir' => isset($result['diagnostic']['native_is_dir']) ? $result['diagnostic']['native_is_dir'] : null,
                ]);
            }

            return $this->returnResponse($result);
        } catch (\Throwable $e) {
            $trace->addTrace('restore_failed', ['code' => 'unknown_error', 'error' => $e->getMessage()]);
            return $this->returnResponse([
                'code' => 'unknown_error',
                'message' => 'Unable to restore the plugin',
            ]);
        }
    }
}
