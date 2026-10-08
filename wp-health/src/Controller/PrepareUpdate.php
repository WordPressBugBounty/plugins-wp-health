<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

class PrepareUpdate extends AbstractController
{
    /**
     * Dedicated backup step for plugin/theme updates.
     * Called as a separate HTTP request before the actual update,
     * giving the backup its own full timeout budget.
     */
    public function executeGet($params)
    {
        return $this->executePost($params);
    }

    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;
        $theme = isset($params['theme']) ? $params['theme'] : null;
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;
        $expectedVersion = isset($params['expected_version']) && is_string($params['expected_version']) ? $params['expected_version'] : null;

        if (!$plugin && !$theme) {
            return $this->returnResponse([
                'status' => 'backup_failed',
                'code' => 'missing_parameters',
                'message' => 'Either plugin or theme parameter is required',
                'current_version' => null,
                'backup_verified' => false,
            ], 400);
        }

        if ($plugin) {
            return $this->preparePluginUpdate($plugin, $lockToken, $expectedVersion);
        }

        return $this->prepareThemeUpdate($theme, $lockToken, $expectedVersion);
    }

    protected function preparePluginUpdate($plugin, $lockToken = null, $expectedVersion = null)
    {
        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('prepare_update_started', ['plugin' => $plugin]);

        $pluginSlug = dirname($plugin);

        return $this->prepareWithLock('plugin', $pluginSlug, $pluginSlug, $lockToken, $trace, function () use ($plugin, $pluginSlug, $trace, $expectedVersion) {
            return $this->backupPlugin($plugin, $pluginSlug, $trace, $expectedVersion);
        });
    }

    protected function prepareThemeUpdate($theme, $lockToken = null, $expectedVersion = null)
    {
        $trace = wp_umbrella_get_service('RequestTrace');
        $trace->addTrace('prepare_update_started', ['theme' => $theme]);

        $lockSlug = wp_umbrella_get_service('UpdateLock')->themeSlug($theme);

        return $this->prepareWithLock('theme', $theme, $lockSlug, $lockToken, $trace, function () use ($theme, $trace, $expectedVersion) {
            return $this->backupTheme($theme, $trace, $expectedVersion);
        });
    }

    /**
     * @param string $type plugin|theme
     * @param string $slug
     * @param string $lockSlug
     * @param string|null $lockToken
     * @param object $trace
     * @param callable $backup
     */
    protected function prepareWithLock($type, $slug, $lockSlug, $lockToken, $trace, $backup)
    {
        $hasToken = is_string($lockToken) && $lockToken !== '';
        $lock = wp_umbrella_get_service('UpdateLock');

        if ($hasToken) {
            $acquired = $lock->acquire($lockSlug, $lockToken);
        } else {
            $acquired = $lock->isHeldByOther($lockSlug, null) ? UpdateLock::REFUSED_OTHER : UpdateLock::ACQUIRED;
        }

        if ($acquired !== UpdateLock::ACQUIRED) {
            $trace->addTrace('update_lock_held');
            wp_umbrella_debug_log("PrepareUpdate: update lock active for '{$slug}', returning " . $lock->refusalCode($acquired));
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => $lock->refusalCode($acquired),
                'message' => sprintf('An update is already in progress for %s %s', $type, $slug),
                'holder' => $lock->holderFingerprint($lockSlug),
                'data' => '',
            ]);
        }

        try {
            $response = $backup();
        } catch (\Throwable $e) {
            if ($hasToken) {
                $lock->release($lockSlug, $lockToken);
            }
            throw $e;
        }

        if ($hasToken && $response['status'] !== 'ready') {
            $lock->release($lockSlug, $lockToken);
            $trace->addTrace('update_lock_released');
        }

        return $this->returnResponse($response);
    }

    protected function backupPlugin($plugin, $pluginSlug, $trace, $expectedVersion = null)
    {
        $currentVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
        $trace->addTrace('version_read', ['current_version' => $currentVersion]);

        wp_umbrella_debug_log("PrepareUpdate: starting backup for plugin '{$plugin}' (version: " . ($currentVersion ?: 'unknown') . ")");

        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => $pluginSlug,
            'src' => WP_PLUGIN_DIR,
            'dir' => 'plugins',
            'main_file' => basename($plugin),
            'expected_version' => $expectedVersion,
        ]);
        $trace->addTrace('temp_backup_move', ['success' => $result['success']]);

        if (!$result['success']) {
            $errorDetails = $this->extractErrorDetails($result);
            wp_umbrella_debug_log("PrepareUpdate: backup failed for plugin '{$plugin}': " . ($result['code'] ?? 'unknown') . " | {$errorDetails}");
            $trace->addTrace('backup_failed', [
                'code' => $result['code'] ?? 'unknown',
                'details' => $errorDetails,
            ]);
            return [
                'status' => 'backup_failed',
                'code' => $result['code'] ?? 'fs_temp_backup_move',
                'message' => $result['message'] ?? 'Backup failed',
                'current_version' => $currentVersion,
                'backup_verified' => false,
            ];
        }

        $backupVerified = !empty($result['verified']) && $this->verifyPluginBackup($pluginSlug, $plugin);
        $trace->addTrace('backup_verified', ['verified' => $backupVerified]);

        wp_umbrella_debug_log("PrepareUpdate: backup completed for plugin '{$plugin}' (verified: " . ($backupVerified ? 'true' : 'false') . ")");

        return [
            'status' => 'ready',
            'code' => 'success',
            'current_version' => $currentVersion,
            'backup_verified' => $backupVerified,
        ];
    }

    protected function backupTheme($theme, $trace, $expectedVersion = null)
    {
        $currentVersion = wp_umbrella_get_service('ManageTheme')->getVersionFromThemeFile($theme);
        $trace->addTrace('version_read', ['current_version' => $currentVersion]);

        wp_umbrella_debug_log("PrepareUpdate: starting backup for theme '{$theme}' (version: " . ($currentVersion ?: 'unknown') . ")");

        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => $theme,
            'src' => get_theme_root($theme),
            'dir' => 'themes',
            'main_file' => 'style.css',
            'expected_version' => $expectedVersion,
        ]);
        $trace->addTrace('temp_backup_move', ['success' => $result['success']]);

        if (!$result['success']) {
            $errorDetails = $this->extractErrorDetails($result);
            wp_umbrella_debug_log("PrepareUpdate: backup failed for theme '{$theme}': " . ($result['code'] ?? 'unknown') . " | {$errorDetails}");
            $trace->addTrace('backup_failed', [
                'code' => $result['code'] ?? 'unknown',
                'details' => $errorDetails,
            ]);
            return [
                'status' => 'backup_failed',
                'code' => $result['code'] ?? 'fs_temp_backup_move',
                'message' => $result['message'] ?? 'Backup failed',
                'current_version' => $currentVersion,
                'backup_verified' => false,
            ];
        }

        $backupVerified = !empty($result['verified']) && $this->verifyThemeBackup($theme);
        $trace->addTrace('backup_verified', ['verified' => $backupVerified]);

        wp_umbrella_debug_log("PrepareUpdate: backup completed for theme '{$theme}' (verified: " . ($backupVerified ? 'true' : 'false') . ")");

        return [
            'status' => 'ready',
            'code' => 'success',
            'current_version' => $currentVersion,
            'backup_verified' => $backupVerified,
        ];
    }

    /**
     * Extract diagnostic details from a failed backup result for tracing.
     */
    protected function extractErrorDetails($result)
    {
        $parts = [];

        if (!empty($result['wp_error'])) {
            $parts[] = 'wp_error:' . $result['wp_error'];
        }

        if (!empty($result['errors'])) {
            $parts[] = 'copy_errors:' . implode(' | ', array_slice($result['errors'], 0, 3));
        }

        if (!empty($result['src'])) {
            $parts[] = 'src:' . $result['src'];
        }

        if (!empty($result['dest'])) {
            $parts[] = 'dest:' . $result['dest'];
        }

        if (!empty($result['src_dir_resolved'])) {
            $parts[] = 'src_dir_resolved:' . $result['src_dir_resolved'];
        }

        if (!empty($result['src_dir_original'])) {
            $parts[] = 'src_dir_original:' . $result['src_dir_original'];
        }

        return implode(' ; ', $parts);
    }

    /**
     * Verify the plugin backup has a reasonable file count and the main plugin file.
     */
    protected function verifyPluginBackup($pluginSlug, $pluginFile)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $backupDir = $wp_filesystem->wp_content_dir() . 'umbrella-upgrade-temp-backup/plugins/' . $pluginSlug;

        if (!$wp_filesystem->is_dir($backupDir)) {
            return false;
        }

        // Check that the main plugin file exists in the backup
        $mainFile = basename($pluginFile);
        if (!$wp_filesystem->exists($backupDir . '/' . $mainFile)) {
            return false;
        }

        return true;
    }

    /**
     * Verify the theme backup has a reasonable file count and the style.css file.
     */
    protected function verifyThemeBackup($theme)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $backupDir = $wp_filesystem->wp_content_dir() . 'umbrella-upgrade-temp-backup/themes/' . $theme;

        if (!$wp_filesystem->is_dir($backupDir)) {
            return false;
        }

        // Check that style.css exists in the backup (required for any theme)
        if (!$wp_filesystem->exists($backupDir . '/style.css')) {
            return false;
        }

        return true;
    }
}
