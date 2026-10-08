<?php
namespace WPUmbrella\Controller\Theme;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

class MoveOldTheme extends AbstractController
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
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No theme'], 400);
        }

        $trace = wp_umbrella_get_service('RequestTrace');
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;
        $hasToken = is_string($lockToken) && $lockToken !== '';
        $lock = wp_umbrella_get_service('UpdateLock');
        $lockSlug = $lock->themeSlug($theme);
        $themeDirectory = get_theme_root($theme) . DIRECTORY_SEPARATOR . $theme;

        $trace->addTrace('restore_started', [
            'theme' => $theme,
            'theme_dir_exists' => is_dir($themeDirectory),
            'style_exists' => file_exists($themeDirectory . DIRECTORY_SEPARATOR . 'style.css'),
        ]);

        if ($lock->isHeldByOther($lockSlug, $lockToken) && $lock->phase($lockSlug) === UpdateLock::PHASE_UPDATING) {
            $trace->addTrace('update_lock_held');
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress',
                'message' => sprintf('An update is already in progress for theme %s', $theme),
                'holder' => $lock->holderFingerprint($lockSlug),
                'data' => '',
            ]);
        }

        try {
            $result = wp_umbrella_get_service('UpgraderTempBackup')->rollbackBackupDir([
                'dir' => 'themes',
                'slug' => $theme,
            ]);

            if (!empty($result['success']) && $hasToken && $lock->phase($lockSlug) !== UpdateLock::PHASE_UPDATING) {
                $lock->release($lockSlug, $lockToken);
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
                'message' => 'Unable to restore the theme',
            ]);
        }
    }
}
