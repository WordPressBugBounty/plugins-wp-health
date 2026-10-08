<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

class CleanupThemeSafeUpdate extends AbstractController
{
    /**
     * We use GET method to set the backup version of the plugin because
     * too many hosts block POST or PUT requests unnecessarily.
     */
    public function executeGet($params)
    {
        // Like "twentytwentyfour"
        $theme = isset($params['theme']) ? $params['theme'] : null;

        if (!$theme) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'missing_parameters',
            ]);
        }

        $trace = wp_umbrella_get_service('RequestTrace');
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;
        $hasToken = is_string($lockToken) && $lockToken !== '';
        $lock = wp_umbrella_get_service('UpdateLock');
        $lockSlug = $lock->themeSlug($theme);

        $trace->addTrace('cleanup_started', ['theme' => $theme]);

        if ($lock->isHeldByOther($lockSlug, $lockToken)) {
            $trace->addTrace('update_lock_held');
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress',
                'message' => sprintf('An update is already in progress for theme %s', $theme),
                'holder' => $lock->holderFingerprint($lockSlug),
                'data' => '',
            ]);
        }

        if ($lock->phase($lockSlug) === UpdateLock::PHASE_UPDATING) {
            $trace->addTrace('update_lock_self_updating');
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress_self',
                'message' => sprintf('An update is still running for theme %s', $theme),
                'holder' => $lock->holderFingerprint($lockSlug),
                'data' => '',
            ]);
        }

        $response = wp_umbrella_get_service('UpgraderTempBackup')->deleteTempBackup([
            'slug' => $theme,
            'dir' => 'themes'
        ]);
        $trace->addTrace('temp_backup_deleted', ['success' => $response['success'] ?? false]);

        // Clean up the update state option as well
        wp_umbrella_get_service('UpdateStateManager')->deleteState($theme, 'theme');
        $trace->addTrace('state_deleted');

        if ($hasToken) {
            $lock->release($lockSlug, $lockToken);
            $trace->addTrace('update_lock_released');
        }

        return $this->returnResponse($response);
    }
}
