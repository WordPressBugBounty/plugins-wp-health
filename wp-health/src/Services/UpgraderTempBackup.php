<?php
namespace WPUmbrella\Services;

use WP_Error;

class UpgraderTempBackup
{
    protected $dirName = 'umbrella-upgrade-temp-backup';

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

        // Create the temporary backup directory if it does not exist.
        if (!$wp_filesystem->is_dir($destDirectory)) {
            if (!$wp_filesystem->is_dir($destDirectory)) {
                $wp_filesystem->mkdir($destDirectory, FS_CHMOD_DIR);
            }

            if (!$wp_filesystem->is_dir($destDirectory)) {
                // Could not create the backup directory.
                return [
                    'code' => 'fs_backup_mkdir',
                    'success' => false
                ];
            }
        }

        // copy to the temporary backup directory.
        $result = copy_dir($srcDirectory, $destDirectory);
        if (is_wp_error($result)) {
            return [
                'code' => 'fs_temp_backup_move',
                'success' => false
            ];
        }

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
        $src = trailingslashit($src_dir) . $args['slug'];
        $dest = $dest_dir . trailingslashit($args['dir']) . $args['slug'];

        // Delete the temporary backup directory if it already exists.
        if ($wp_filesystem->is_dir($dest)) {
            $wp_filesystem->delete($dest, true);
        }

        // copy to the temporary backup directory.
        $result = copy_dir($src, $dest);

        if (is_wp_error($result)) {
            return [
                'code' => 'fs_temp_backup_move',
                'success' => false
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
}
