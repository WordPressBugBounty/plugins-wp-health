<?php
namespace WPUmbrella\Services;

use WP_Error;

class UpgraderTempBackup
{
    const STALE_BACKUP_MAX_AGE = 604800;

    protected $dirName = 'umbrella-upgrade-temp-backup';

    const MANIFEST_FILE = '.umbrella-backup-manifest.json';

    protected static $staleBackupsSwept = false;

    protected $copyRetryBudget = 20;

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

        $invalid = $this->validateTarget($args);
        if ($invalid !== null) {
            return $invalid;
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
                'success' => false,
                'diagnostic' => $this->notFoundDiagnostic($srcDirectory, $args),
            ];
        }

        $trace = wp_umbrella_get_service('RequestTrace');

        $incompleteReason = $this->backupIncompleteReason($srcDirectory);
        if ($incompleteReason === 'no_manifest') {
            $trace->addTrace('temp_backup_unverified', ['slug' => $args['slug'], 'dir' => $args['dir']]);
        } elseif ($incompleteReason !== null) {
            $trace->addTrace('temp_backup_incomplete', ['slug' => $args['slug'], 'dir' => $args['dir'], 'stage' => 'rollback', 'reason' => $incompleteReason]);
            return [
                'code' => 'temp_backup_incomplete',
                'success' => false
            ];
        }

        $baseDirectory = $args['dir'] === 'plugins' ? WP_PLUGIN_DIR : get_theme_root($args['slug']);
        $destDirectory = $baseDirectory . DIRECTORY_SEPARATOR . $args['slug'];

        $asideDirectory = $wp_filesystem->wp_content_dir() . $this->dirName . DIRECTORY_SEPARATOR . $args['dir'] . DIRECTORY_SEPARATOR . $args['slug'] . '-replaced';
        $movedAside = false;
        $restored = false;

        try {
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
                $wp_filesystem->touch($asideDirectory);
            }

            // Use move_dir() for an atomic rename — same approach as WP core's restore_temp_backup().
            // Falls back to copy_dir + delete internally if rename() is not possible.
            $trace->addTrace('rollback_move_dir', ['src' => $args['slug'], 'dir' => $args['dir']]);
            $result = move_dir($srcDirectory, $destDirectory, true);

            if (is_wp_error($result)) {
                $trace->addTrace('rollback_move_dir_failed', ['error' => $result->get_error_message(), 'code' => $result->get_error_code()]);
                return [
                    'code' => 'fs_temp_backup_move',
                    'success' => false
                ];
            }

            $restored = true;
        } finally {
            if ($movedAside && !$restored) {
                $this->putAsideBack($asideDirectory, $destDirectory, $srcDirectory, $args, $trace);
            }
        }

        if ($movedAside) {
            $wp_filesystem->delete($asideDirectory, true);
        }

        $manifestPath = $destDirectory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        if ($wp_filesystem->exists($manifestPath)) {
            $wp_filesystem->delete($manifestPath);
        }

        $trace->addTrace('rollback_move_dir_success');
        return [
            'success' => true
        ];
    }

    protected function putAsideBack($asideDirectory, $destDirectory, $srcDirectory, $args, $trace)
    {
        global $wp_filesystem;

        try {
            if ($wp_filesystem->is_dir($destDirectory) && $this->isWholeBackup($destDirectory, $args, !$wp_filesystem->is_dir($srcDirectory))) {
                $trace->addTrace('rollback_dest_kept', ['dest' => $args['slug'], 'dir' => $args['dir']]);

                $manifestPath = $destDirectory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
                if ($wp_filesystem->exists($manifestPath)) {
                    $wp_filesystem->delete($manifestPath);
                }

                return;
            }

            if ($wp_filesystem->is_dir($destDirectory)) {
                $trace->addTrace('rollback_partial_dest_removed', ['dest' => $args['slug'], 'dir' => $args['dir']]);
                $wp_filesystem->delete($destDirectory, true);
            }

            $trace->addTrace('rollback_restore_dest', ['dest' => $args['slug'], 'dir' => $args['dir']]);
            $restore = move_dir($asideDirectory, $destDirectory, true);

            if (is_wp_error($restore)) {
                $trace->addTrace('rollback_restore_dest_failed', ['dest' => $args['slug'], 'error' => $restore->get_error_message()]);
            }
        } catch (\Throwable $e) {
            $trace->addTrace('rollback_restore_dest_failed', ['dest' => $args['slug'], 'error' => $e->getMessage()]);
        }
    }

    protected function isWholeBackup($directory, $args, $sourceGone)
    {
        global $wp_filesystem;

        $reason = $this->backupIncompleteReason($directory);

        if ($reason === null) {
            return true;
        }

        if ($reason !== 'no_manifest' || !$sourceGone) {
            return false;
        }

        $mainFile = $this->mainFileFromArgs($args);

        if ($mainFile === null) {
            return $this->extractVersionFromDir($directory) !== null;
        }

        return $wp_filesystem->exists($directory . '/' . $mainFile);
    }

    protected function mainFileFromArgs($args)
    {
        if (!empty($args['main_file']) && is_string($args['main_file'])) {
            return basename($args['main_file']);
        }

        return $args['dir'] === 'themes' ? 'style.css' : null;
    }

    protected function notFoundDiagnostic($srcDirectory, $args)
    {
        global $wp_filesystem;

        $diagnostic = [
            'fs_method' => is_object($wp_filesystem) && isset($wp_filesystem->method) ? $wp_filesystem->method : null,
            'fs_class' => is_object($wp_filesystem) ? get_class($wp_filesystem) : null,
            'src' => $srcDirectory,
        ];

        if (!defined('WP_CONTENT_DIR')) {
            return $diagnostic;
        }

        $nativeDirectory = WP_CONTENT_DIR . '/' . $this->dirName . '/' . $args['dir'] . '/' . $args['slug'];
        @clearstatcache(true, $nativeDirectory);
        $diagnostic['native_path'] = $nativeDirectory;
        $diagnostic['native_is_dir'] = @is_dir($nativeDirectory);

        if (!empty($args['main_file'])) {
            $diagnostic['native_main_file_exists'] = @file_exists($nativeDirectory . '/' . basename($args['main_file']));
        }

        return $diagnostic;
    }

    /**
     * @from wp-admin/includes/class-wp-upgrader.php
     * Move a plugin or theme to a temporary backup directory.
     * @param array $args {
     *  Arguments for moving a plugin or theme to a temporary backup directory.
     *  @type string $slug Plugin or theme slug.
     *  @type string $src Source directory.
     * 	@type string $dir Destination directory.
     *  @type string $main_file Optional. Plugin main file basename, or style.css for a theme.
     *  @type string $expected_version Optional. Version the caller expects to be installed.
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

        $invalid = $this->validateTarget($args);
        if ($invalid !== null) {
            return $invalid;
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

        $this->ensureRootGuard($dest_dir);

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

        $trace = wp_umbrella_get_service('RequestTrace');
        $mainFile = !empty($args['main_file']) && is_string($args['main_file']) ? basename($args['main_file']) : null;
        $expectedVersion = isset($args['expected_version']) && is_string($args['expected_version']) && trim($args['expected_version']) !== ''
            ? trim($args['expected_version'])
            : null;
        $sourceVersion = $this->readVersion($src, $mainFile);
        $replacing = $wp_filesystem->is_dir($dest);

        if ($replacing) {
            $incompleteReason = $this->backupIncompleteReason($dest);
            $backupVersion = $incompleteReason === null ? $this->readVersion($dest, $mainFile) : null;

            if ($backupVersion !== null && $backupVersion === $sourceVersion) {
                $wp_filesystem->touch($dest);
                $this->recordManifestUse($dest);
                $trace->addTrace('temp_backup_preserved', ['slug' => $args['slug'], 'version' => $backupVersion]);
                return [
                    'success' => true,
                    'code' => 'backup_already_exists',
                    'verified' => true,
                ];
            }

            if ($backupVersion !== null && $backupVersion === $expectedVersion) {
                $trace->addTrace('temp_backup_preserved', [
                    'slug' => $args['slug'],
                    'version' => $backupVersion,
                    'installed_version' => $sourceVersion,
                ]);
                return [
                    'code' => 'fs_temp_backup_source_version_mismatch',
                    'success' => false,
                    'message' => sprintf('Installed version %s differs from the expected version %s, the existing backup is kept.', $sourceVersion === null ? 'unknown' : $sourceVersion, $expectedVersion),
                    'src' => $src,
                ];
            }

            if ($incompleteReason !== null) {
                $trace->addTrace('temp_backup_incomplete', ['slug' => $args['slug'], 'stage' => 'existing', 'reason' => $incompleteReason]);
            }

            $sourceMainFileMissing = $mainFile !== null && !$wp_filesystem->exists($src . '/' . $mainFile);

            if ($sourceMainFileMissing || ($sourceVersion === null && $incompleteReason === null)) {
                return [
                    'code' => 'fs_temp_backup_source_unreadable',
                    'success' => false,
                    'message' => 'The installed version cannot be read, the existing backup is kept.',
                    'src' => $src,
                ];
            }
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $sourceListing = $this->contentListing($src);

            if ($sourceListing === null) {
                return [
                    'code' => 'fs_temp_backup_incomplete',
                    'success' => false,
                    'message' => 'Backup incomplete: the installed files cannot be listed.',
                ];
            }

            $startedAt = microtime(true);
            $copy = $this->copyToStaging($src, $dest);
            $elapsed = microtime(true) - $startedAt;

            if (!$copy['success']) {
                return $copy;
            }

            $staging = $copy['staging'];
            $stagingListing = $this->contentListing($staging);
            $missing = $this->missingFromCopy($sourceListing, $stagingListing);

            if ($missing === []) {
                break;
            }

            $wp_filesystem->delete($staging, true);
            $trace->addTrace('temp_backup_incomplete', [
                'slug' => $args['slug'],
                'stage' => 'copy',
                'attempt' => $attempt,
                'copy_seconds' => round($elapsed, 1),
                'missing' => $missing === null ? null : array_slice($missing, 0, 10),
            ]);

            if ($attempt === 2 || $elapsed >= $this->copyRetryBudget) {
                return [
                    'code' => 'fs_temp_backup_incomplete',
                    'success' => false,
                    'message' => 'Backup incomplete: the copy does not match the installed files.',
                ];
            }
        }

        $stagingStats = $this->statsFromListing($stagingListing);
        $manifest = wp_json_encode([
            'version' => $sourceVersion,
            'files' => $stagingStats['files'],
            'bytes' => $stagingStats['bytes'],
            'created_at' => time(),
        ]);

        if (!is_string($manifest) || $manifest === '' || !$wp_filesystem->put_contents($staging . '/' . self::MANIFEST_FILE, $manifest, FS_CHMOD_FILE)) {
            $wp_filesystem->delete($staging, true);

            return [
                'code' => 'fs_temp_backup_manifest',
                'success' => false,
                'dest' => $staging,
            ];
        }

        if ($replacing) {
            $wp_filesystem->delete($dest, true);
        }

        if ($wp_filesystem->is_dir($dest) || !$wp_filesystem->move($staging, $dest)) {
            $wp_filesystem->delete($staging, true);

            return [
                'code' => 'fs_temp_backup_publish',
                'success' => false,
                'dest' => $dest,
            ];
        }

        if ($replacing) {
            $trace->addTrace('temp_backup_replaced', ['slug' => $args['slug'], 'version' => $sourceVersion]);
        }

        return [
            'success' => true,
            'verified' => true,
        ];
    }

    protected function ensureRootGuard($rootDir)
    {
        global $wp_filesystem;

        if (!$wp_filesystem->exists($rootDir . 'index.php')) {
            $wp_filesystem->put_contents($rootDir . 'index.php', '<?php // Silence is golden.', FS_CHMOD_FILE);
        }

        if (!$wp_filesystem->exists($rootDir . '.htaccess')) {
            $lines = wp_umbrella_get_service('HtaccessFile')->getDenyLines();
            $wp_filesystem->put_contents($rootDir . '.htaccess', implode("\n", $lines) . "\n", FS_CHMOD_FILE);
        }
    }

    /**
     * Copy the source into a fresh staging directory next to the final backup.
     *
     * @return array ['success' => true, 'staging' => string] or a failure result.
     */
    protected function copyToStaging($src, $dest)
    {
        global $wp_filesystem;

        $this->deleteOrphanStagings($dest);

        $staging = $dest . '.partial-' . uniqid();

        if (!$wp_filesystem->mkdir($staging, FS_CHMOD_DIR)) {
            return [
                'code' => 'fs_temp_backup_mkdir_dest',
                'success' => false,
                'dest' => $staging,
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

        try {
            $result = copy_dir($src, $staging);
        } finally {
            restore_error_handler();
        }

        if (is_wp_error($result)) {
            $wp_filesystem->delete($staging, true);

            return [
                'code' => 'fs_temp_backup_move',
                'success' => false,
                'errors' => $copyFailures,
                'wp_error' => $result->get_error_message(),
                'src' => $src,
                'dest' => $staging,
            ];
        }

        if (!empty($copyFailures)) {
            $wp_filesystem->delete($staging, true);

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
            'success' => true,
            'staging' => $staging,
        ];
    }

    protected function deleteOrphanStagings($dest)
    {
        global $wp_filesystem;

        $entries = $wp_filesystem->dirlist(dirname($dest));

        if (!is_array($entries)) {
            return;
        }

        $prefix = basename($dest) . '.partial-';

        foreach ($entries as $name => $entry) {
            if (isset($entry['type']) && $entry['type'] === 'd' && strpos($name, $prefix) === 0) {
                $wp_filesystem->delete(dirname($dest) . '/' . $name, true);
            }
        }
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
    public function hasTempBackup($args)
    {
        global $wp_filesystem;

        if ($wp_filesystem === null) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (empty($args['slug']) || empty($args['dir']) || $this->validateTarget($args) !== null || !$wp_filesystem->wp_content_dir()) {
            return false;
        }

        return $wp_filesystem->is_dir($wp_filesystem->wp_content_dir() . $this->dirName . DIRECTORY_SEPARATOR . $args['dir'] . DIRECTORY_SEPARATOR . $args['slug']);
    }

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

        $invalid = $this->validateTarget($args);
        if ($invalid !== null) {
            return $invalid;
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

    protected function validateTarget($args)
    {
        if (!in_array($args['dir'], ['plugins', 'themes'], true)) {
            return [
                'code' => 'invalid_parameters',
                'success' => false
            ];
        }

        if (!is_string($args['slug']) || rtrim($args['slug'], ' .') === '' || preg_match('/[\/\\\\\x00-\x1f\x7f]/', $args['slug'])) {
            return [
                'code' => 'invalid_plugin_slug',
                'success' => false
            ];
        }

        return null;
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

                $entryPath = $typeDir . DIRECTORY_SEPARATOR . $name;
                $lastModified = $this->lastUsedAt($entryPath, $entry);

                if ($lastModified <= 0 || $lastModified > $threshold) {
                    continue;
                }

                if (substr($name, -9) === '-replaced') {
                    $slug = substr($name, 0, -9);
                    $installedDirectory = ($type === 'plugins' ? WP_PLUGIN_DIR : get_theme_root($slug)) . DIRECTORY_SEPARATOR . $slug;

                    $lock = wp_umbrella_get_service('UpdateLock');
                    $lockSlug = $type === 'themes' ? $lock->themeSlug($slug) : $slug;

                    if (!$wp_filesystem->is_dir($installedDirectory) || $lock->phase($lockSlug) !== null) {
                        continue;
                    }
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
     * @return string|null
     */
    protected function backupIncompleteReason($dir)
    {
        global $wp_filesystem;

        $manifestPath = $dir . '/' . self::MANIFEST_FILE;

        if (!$wp_filesystem->exists($manifestPath)) {
            return 'no_manifest';
        }

        $manifest = json_decode((string) $wp_filesystem->get_contents($manifestPath), true);

        if (!is_array($manifest) || !isset($manifest['files'], $manifest['bytes'])) {
            return 'unreadable_manifest';
        }

        $stats = $this->contentStats($dir);

        if ($stats === null || $stats['files'] !== (int) $manifest['files'] || $stats['bytes'] !== (int) $manifest['bytes']) {
            return 'content_mismatch';
        }

        return null;
    }

    protected function recordManifestUse($dir)
    {
        global $wp_filesystem;

        $manifestPath = $dir . '/' . self::MANIFEST_FILE;
        $manifest = json_decode((string) $wp_filesystem->get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            return;
        }

        $manifest['used_at'] = time();
        $encoded = wp_json_encode($manifest);

        if (is_string($encoded) && $encoded !== '') {
            $wp_filesystem->put_contents($manifestPath, $encoded, FS_CHMOD_FILE);
        }
    }

    /**
     * @return int
     */
    protected function lastUsedAt($dir, $entry)
    {
        global $wp_filesystem;

        $lastModified = isset($entry['lastmodunix']) ? (int) $entry['lastmodunix'] : 0;
        $manifestPath = $dir . '/' . self::MANIFEST_FILE;

        if (!$wp_filesystem->exists($manifestPath)) {
            return $lastModified;
        }

        $manifest = json_decode((string) $wp_filesystem->get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            return $lastModified;
        }

        foreach (['created_at', 'used_at'] as $key) {
            if (isset($manifest[$key])) {
                $lastModified = max($lastModified, (int) $manifest[$key]);
            }
        }

        return $lastModified;
    }

    protected function contentListing($dir, $prefix = '')
    {
        global $wp_filesystem;

        $entries = $wp_filesystem->dirlist($dir);

        if (!is_array($entries)) {
            return null;
        }

        $listing = [];

        foreach ($entries as $name => $entry) {
            if ($prefix === '' && $name === self::MANIFEST_FILE) {
                continue;
            }

            $type = isset($entry['type']) ? $entry['type'] : '';

            if ($type === 'f') {
                $listing[$prefix . $name] = isset($entry['size']) ? (int) $entry['size'] : 0;
                continue;
            }

            if ($type !== 'd') {
                continue;
            }

            $child = $this->contentListing(trailingslashit($dir) . $name, $prefix . $name . '/');

            if ($child === null) {
                return null;
            }

            $listing += $child;
        }

        return $listing;
    }

    /**
     * @return array|null ['files' => int, 'bytes' => int]
     */
    protected function contentStats($dir)
    {
        $listing = $this->contentListing($dir);

        return $listing === null ? null : $this->statsFromListing($listing);
    }

    protected function statsFromListing($listing)
    {
        return [
            'files' => count($listing),
            'bytes' => (int) array_sum($listing),
        ];
    }

    /**
     * @return array|null
     */
    protected function missingFromCopy($sourceListing, $copyListing)
    {
        if ($copyListing === null) {
            return null;
        }

        $missing = [];

        foreach ($sourceListing as $path => $size) {
            if (!isset($copyListing[$path]) || $copyListing[$path] !== $size) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    protected function readVersion($dir, $mainFile = null)
    {
        global $wp_filesystem;

        if ($mainFile === null) {
            return $this->extractVersionFromDir($dir);
        }

        $path = $dir . '/' . $mainFile;

        if (!$wp_filesystem->exists($path)) {
            return null;
        }

        $content = $wp_filesystem->get_contents($path);

        if (!is_string($content) || $content === '') {
            return null;
        }

        $content = str_replace("\r", "\n", substr($content, 0, 8192));

        if (!preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*Version:(.*)$/mi', $content, $matches)) {
            return null;
        }

        $version = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $matches[1]));

        return $version === '' ? null : $version;
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
