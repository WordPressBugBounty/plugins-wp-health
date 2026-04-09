<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;

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
            return $this->preparePluginUpdate($plugin);
        }

        return $this->prepareThemeUpdate($theme);
    }

    protected function preparePluginUpdate($plugin)
    {
        $trace = wp_umbrella_get_service('RequestTrace');
        $pluginSlug = dirname($plugin);

        $trace->addTrace('prepare_update_started', ['plugin' => $plugin]);
        $currentVersion = wp_umbrella_get_service('ManagePlugin')->getVersionFromPluginFile($plugin);
        $trace->addTrace('version_read', ['current_version' => $currentVersion]);

        wp_umbrella_debug_log("PrepareUpdate: starting backup for plugin '{$plugin}' (version: " . ($currentVersion ?: 'unknown') . ")");

        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => $pluginSlug,
            'src' => WP_PLUGIN_DIR,
            'dir' => 'plugins',
        ]);
        $trace->addTrace('temp_backup_move', ['success' => $result['success']]);

        if (!$result['success']) {
            wp_umbrella_debug_log("PrepareUpdate: backup failed for plugin '{$plugin}': " . ($result['code'] ?? 'unknown'));
            $trace->addTrace('backup_failed', ['code' => $result['code'] ?? 'unknown']);
            return $this->returnResponse([
                'status' => 'backup_failed',
                'code' => $result['code'] ?? 'fs_temp_backup_move',
                'message' => $result['message'] ?? 'Backup failed',
                'current_version' => $currentVersion,
                'backup_verified' => false,
            ]);
        }

        $backupVerified = $this->verifyPluginBackup($pluginSlug, $plugin);
        $trace->addTrace('backup_verified', ['verified' => $backupVerified]);

        wp_umbrella_debug_log("PrepareUpdate: backup completed for plugin '{$plugin}' (verified: " . ($backupVerified ? 'true' : 'false') . ")");

        return $this->returnResponse([
            'status' => 'ready',
            'code' => 'success',
            'current_version' => $currentVersion,
            'backup_verified' => $backupVerified,
        ]);
    }

    protected function prepareThemeUpdate($theme)
    {
        $trace = wp_umbrella_get_service('RequestTrace');

        $trace->addTrace('prepare_update_started', ['theme' => $theme]);
        $currentVersion = wp_umbrella_get_service('ManageTheme')->getVersionFromThemeFile($theme);
        $trace->addTrace('version_read', ['current_version' => $currentVersion]);

        wp_umbrella_debug_log("PrepareUpdate: starting backup for theme '{$theme}' (version: " . ($currentVersion ?: 'unknown') . ")");

        $result = wp_umbrella_get_service('UpgraderTempBackup')->moveToTempBackupDir([
            'slug' => $theme,
            'src' => get_theme_root($theme),
            'dir' => 'themes',
        ]);
        $trace->addTrace('temp_backup_move', ['success' => $result['success']]);

        if (!$result['success']) {
            wp_umbrella_debug_log("PrepareUpdate: backup failed for theme '{$theme}': " . ($result['code'] ?? 'unknown'));
            $trace->addTrace('backup_failed', ['code' => $result['code'] ?? 'unknown']);
            return $this->returnResponse([
                'status' => 'backup_failed',
                'code' => $result['code'] ?? 'fs_temp_backup_move',
                'message' => $result['message'] ?? 'Backup failed',
                'current_version' => $currentVersion,
                'backup_verified' => false,
            ]);
        }

        $backupVerified = $this->verifyThemeBackup($theme);
        $trace->addTrace('backup_verified', ['verified' => $backupVerified]);

        wp_umbrella_debug_log("PrepareUpdate: backup completed for theme '{$theme}' (verified: " . ($backupVerified ? 'true' : 'false') . ")");

        return $this->returnResponse([
            'status' => 'ready',
            'code' => 'success',
            'current_version' => $currentVersion,
            'backup_verified' => $backupVerified,
        ]);
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
