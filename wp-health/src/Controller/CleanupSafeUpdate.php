<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

class CleanupSafeUpdate extends AbstractController
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
            return $this->returnResponse([
                'success' => false,
                'code' => 'missing_parameters',
            ]);
        }

        $trace = wp_umbrella_get_service('RequestTrace');
        $pluginSlug = dirname($plugin);
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;
        $hasToken = is_string($lockToken) && $lockToken !== '';
        $lock = wp_umbrella_get_service('UpdateLock');

        $trace->addTrace('cleanup_started', ['plugin' => $plugin]);

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

        $response = wp_umbrella_get_service('UpgraderTempBackup')->deleteTempBackup([
            'slug' => $pluginSlug,
            'dir' => 'plugins'
        ]);
        $trace->addTrace('temp_backup_deleted', ['success' => $response['success'] ?? false]);

        // Clean up the update state option as well
        wp_umbrella_get_service('UpdateStateManager')->deleteState($pluginSlug, 'plugin');
        $trace->addTrace('state_deleted');

        if ($hasToken) {
            $lock->release($pluginSlug, $lockToken);
            $trace->addTrace('update_lock_released');
        }

        return $this->returnResponse($response);
    }
}
