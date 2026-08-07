<?php
namespace WPUmbrella\Services;

use WP_Error;

class UpgraderTempBackup
{
    const STALE_BACKUP_MAX_AGE = 604800;

    protected $dirName = 'umbrella-upgrade-temp-backup';

    protected static $staleBackupsSwept = false;

    public function rollbackBackupDir($args)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (empty($args['slug']) || empty($args['dir'])) {
            return [
                'code' => 'missing_parameters',
                'success' => false
            ];
        }

        if (!$wp_filesystem->wp_content_dir()) {
            return [
                'code' => 'fs_no_content_dir',
                'success' => false
            ];
        }

        $srcDirectory = $wp_filesystem->wp_content_dir() . $this->dirName . DIRECTORY_SEPARATOR . $args['dir'] . DIRECTORY_SEPARATOR . $args['slug'];

        if (!$wp_filesystem->is_dir($srcDirectory)) {
            return [
                'code' => 'temp_backup_not_found',
                'success' => false
            ];
        }

        $baseDirectory = $args['dir'] === 'plugins' ? WP_PLUGIN_DIR : get_theme_root($args['slug']);
        $destDirectory = $baseDirectory . DIRECTORY_SEPARATOR . $args['slug'];
        $trace = wp_umbrella_get_service('RequestTrace');

        // Move the current version aside instead of deleting it: if the restore
        // dies mid-way, the site still has a directory to put back. The aside
        // copy lives in the temp backup tree, so the stale sweep cleans up any
        // leftover and WordPress never scans it as an installed plugin.
        $asideDirectory = $wp_filesystem->wp_content_dir() . $this->dirName . DIRECTORY_SEPARATOR . $args['dir'] . DIRECTORY_SEPARATOR . $args['slug'] . '-replaced';
        $movedAside = false;

        if ($wp_filesystem->is_dir($destDirectory)) {
            if ($wp_filesystem->is_dir($asideDirectory)) {
                $wp_filesystem->delete($asideDirectory, true);
            }

            $trace->addTrace('rollback_move_dest_aside', ['dest' => $args['slug'], 'dir' => $args['dir']]);
            $aside = move_dir($destDirectory, $asideDirectory, true);
            if (is_wp_error($aside)) {
                $trace->addTrace('rollback_move_dest_aside_failed', ['error' => $aside->get_error_message()]);
                return [
                    'code' => 'fs_temp_backup_move_aside',
                    'success' => false
                ];
            }
            $movedAside = true;
        }

        // Use move_dir() for an atomic rename — same approach as WP core's restore_temp_backup().
        // Falls back to copy_dir + delete internally if rename() is not possible.
        $trace->addTrace('rollback_move_dir', ['src' => $args['slug'], 'dir' => $args['dir']]);
        $result = move_dir($srcDirectory, $destDirectory, true);
        if (is_wp_error($result)) {
            $trace->addTrace('rollback_move_dir_failed', ['error' => $result->get_error_message()]);

            if ($movedAside && !$wp_filesystem->is_dir($destDirectory)) {
                $trace->addTrace('rollback_restore_dest', ['dest' => $args['slug'], 'dir' => $args['dir']]);
                move_dir($asideDirectory, $destDirectory, true);
            }

            return [
                'code' => 'fs_temp_backup_move',
                'success' => false
            ];
        }

        if ($movedAside) {
            $wp_filesystem->delete($asideDirectory, true);
        }

        $trace->addTrace('rollback_move_dir_success');
        return [
            'success' => true
        ];
    }

    /**
     * @from wp-admin/includes/class-wp-upgrader.php
     * Move a plugin or theme to a temporary backup directory.
     * @param array $args {
     *  Arguments for moving a plugin or theme to a temporary backup directory.
     *  @type string $slug Plugin or theme slug.
     *  @type string $src Source directory.
     * 	@type string $dir Destination directory.
     * }
     */
    public function moveToTempBackupDir($args)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (empty($args['slug']) || empty($args['src']) || empty($args['dir'])) {
            return [
                'code' => 'missing_parameters',
                'success' => false
            ];
        }

        /*
         * Skip any plugin that has "." as its slug.
         * A slug of "." will result in a `$src` value ending in a period.
         *
         * On Windows, this will cause the 'plugins' folder to be moved,
         * and will cause a failure when attempting to call `mkdir()`.
         */
        if ('.' === $args['slug']) {
            return [
                'code' => 'invalid_plugin_slug',
                'success' => false
            ];
        }

        if (!$wp_filesystem->wp_content_dir()) {
            return [
                'code' => 'fs_no_content_dir',
                'success' => false
            ];
        }

        $this->deleteStaleBackups();

        $dest_dir = $wp_filesystem->wp_content_dir() . $this->dirName . '/';
        $sub_dir = $dest_dir . $args['dir'] . '/';

        // Create the temporary backup directory if it does not exist.
        if (!$wp_filesystem->is_dir($sub_dir)) {
            if (!$wp_filesystem->is_dir($dest_dir)) {
                $wp_filesystem->mkdir($dest_dir, FS_CHMOD_DIR);
            }

            if (!$wp_filesystem->mkdir($sub_dir, FS_CHMOD_DIR)) {
                // Could not create the backup directory.
                return [
                    'code' => 'fs_temp_backup_mkdir',
                    'success' => false
                ];
            }
        }

        $src_dir = $wp_filesystem->find_folder($args['src']);

        // find_folder() can return false/empty on non-standard installs (Bedrock, Composer-managed).
        // Fall back to the direct path which relies on WP_PLUGIN_DIR / get_theme_root() already being correct.
        if (empty($src_dir)) {
            $src_dir = trailingslashit($args['src']);
        }

        $src = trailingslashit($src_dir) . $args['slug'];
        $dest = $dest_dir . trailingslashit($args['dir']) . $args['slug'];

        // Early check: if source doesn't exist, fail fast with a diagnostic code
        if (!$wp_filesystem->is_dir($src)) {
            return [
                'code' => 'fs_temp_backup_source_not_found',
                'success' => false,
                'src' => $src,
                'src_dir_resolved' => $src_dir,
                'src_dir_original' => $args['src'],
            ];
        }

        // If a backup already exists, check if it matches the currently installed version.
        // Same version → backup is from the current update cycle (retry scenario) → preserve it.
        // Different version → stale backup from a previous update → overwrite with current version.
        if ($wp_filesystem->is_dir($dest)) {
            $backupVersion = $this->getVersionFromBackup($dest, $args['slug']);
            $currentVersion = $this->getVersionFromSource($src, $args['slug']);

            if ($backupVersion !== null && $currentVersion !== null && $backupVersion === $currentVersion) {
                return [
                    'success' => true,
                    'code' => 'backup_already_exists',
                ];
            }

            // Stale backup or version mismatch — delete and recreate
            $wp_filesystem->delete($dest, true);
        }

        // copy_dir() does not create the top-level destination — its inner mkdir() is non-recursive,
        // so the first sub-directory in $src would fail with "Could not create directory."
        if (!$wp_filesystem->mkdir($dest, FS_CHMOD_DIR)) {
            return [
                'code' => 'fs_temp_backup_mkdir_dest',
                'success' => false,
                'dest' => $dest,
            ];
        }

        // Capture copy() failures during backup (file not copied = incomplete backup)
        $copyFailures = [];
        set_error_handler(function ($errno, $errstr) use (&$copyFailures) {
            if (strpos($errstr, 'copy(') === 0) {
                $copyFailures[] = $errstr;
            }
            return true;
        }, E_WARNING);

        $result = copy_dir($src, $dest);

        restore_error_handler();

        if (is_wp_error($result)) {
            return [
                'code' => 'fs_temp_backup_move',
                'success' => false,
                'errors' => $copyFailures,
                'wp_error' => $result->get_error_message(),
                'src' => $src,
                'dest' => $dest,
            ];
        }

        if (!empty($copyFailures)) {
            return [
                'code' => 'fs_temp_backup_incomplete',
                'success' => false,
                'message' => sprintf(
                    'Backup incomplete: %d file(s) could not be copied.',
                    count($copyFailures)
                ),
                'errors' => array_slice($copyFailures, 0, 10),
            ];
        }

        return [
            'success' => true
        ];
    }

    /**
     * @from wp-admin/includes/class-wp-upgrader.php
     * Delete a plugin or theme from the temporary backup directory
     * @param array $args {
     *  Arguments for moving a plugin or theme to a temporary backup directory.
     *  @type string $slug Plugin or theme slug.
     * 	@type string $dir Destination directory.
     * }.
    */
    public function deleteTempBackup($args)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $errors = new WP_Error();

        if (empty($args['slug']) || empty($args['dir'])) {
            return [
                'code' => 'missing_parameters',
                'success' => false
            ];
        }

        if (!$wp_filesystem->wp_content_dir()) {
            return [
                'code' => 'fs_no_content_dir',
                'success' => false
            ];
        }

        $this->deleteStaleBackups();

        $temp_backup_dir = $wp_filesystem->wp_content_dir() . "{$this->dirName}/{$args['dir']}/{$args['slug']}";

        if (!$wp_filesystem->delete($temp_backup_dir, true)) {
            return [
                'code' => 'temp_backup_delete_failed',
                'success' => false
            ];
        }

        return [
            'code' => 'success',
            'success' => true
        ];
    }

    /**
     * @param int $maxAge Age in seconds.
     * @param bool $force
     */
    public function deleteStaleBackups($maxAge = self::STALE_BACKUP_MAX_AGE, $force = false)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (self::$staleBackupsSwept && !$force) {
            return [
                'success' => true,
                'deleted' => []
            ];
        }

        self::$staleBackupsSwept = true;

        if (!$wp_filesystem->wp_content_dir()) {
            return [
                'code' => 'fs_no_content_dir',
                'success' => false
            ];
        }

        $rootDir = $wp_filesystem->wp_content_dir() . $this->dirName;

        if (!$wp_filesystem->is_dir($rootDir)) {
            return [
                'success' => true,
                'deleted' => []
            ];
        }

        $threshold = time() - $maxAge;
        $deleted = [];

        foreach (['plugins', 'themes'] as $type) {
            $typeDir = $rootDir . DIRECTORY_SEPARATOR . $type;

            if (!$wp_filesystem->is_dir($typeDir)) {
                continue;
            }

            $entries = $wp_filesystem->dirlist($typeDir);

            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $name => $entry) {
                if (!isset($entry['type']) || $entry['type'] !== 'd') {
                    continue;
                }

                $lastModified = isset($entry['lastmodunix']) ? (int) $entry['lastmodunix'] : 0;

                if ($lastModified <= 0 || $lastModified > $threshold) {
                    continue;
                }

                if ($wp_filesystem->delete($typeDir . DIRECTORY_SEPARATOR . $name, true)) {
                    $deleted[] = $type . '/' . $name;
                }
            }
        }

        if (!empty($deleted)) {
            wp_umbrella_get_service('RequestTrace')->addTrace('stale_temp_backups_deleted', [
                'count' => count($deleted),
                'entries' => array_slice($deleted, 0, 10),
            ]);
        }

        return [
            'success' => true,
            'deleted' => $deleted
        ];
    }

    /**
     * Read the plugin/theme version from a backup directory.
     */
    protected function getVersionFromBackup($backupDir, $slug)
    {
        return $this->extractVersionFromDir($backupDir);
    }

    /**
     * Read the plugin/theme version from the currently installed source.
     */
    protected function getVersionFromSource($sourceDir, $slug)
    {
        return $this->extractVersionFromDir($sourceDir);
    }

    /**
     * Extract a Version header from PHP files in a directory (plugin main file or style.css for themes).
     */
    protected function extractVersionFromDir($dir)
    {
        if (!is_dir($dir)) {
            return null;
        }

        // Try style.css first (themes)
        $stylePath = $dir . DIRECTORY_SEPARATOR . 'style.css';
        if (file_exists($stylePath)) {
            $content = file_get_contents($stylePath);
            if ($content && preg_match('/Version:\s*(.+)$/mi', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        // Try PHP files in root (plugins — main file contains the Version header)
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php');
        if ($files) {
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content && preg_match('/Version:\s*(.+)$/mi', $content, $matches)) {
                    return trim($matches[1]);
                }
            }
        }

        return null;
    }

}
