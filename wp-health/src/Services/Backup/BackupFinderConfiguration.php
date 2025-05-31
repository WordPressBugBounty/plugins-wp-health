<?php
namespace WPUmbrella\Services\Backup;

use WPUmbrella\Helpers\Host;
use WPUmbrellaVendor\Symfony\Component\Finder\Finder;

class BackupFinderConfiguration
{
    const MO_IN_BYTES = 1048576;

    /**
     * @return array
     */
    public function getDefaultExcludeFiles()
    {
        return [
            WP_UMBRELLA_DIR_WPU_BACKUP_BOX,
            WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache', // like wp-rocket
            WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'updraft', // backup updraft
            WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'ai1wm-backups', // backup ai1wm-backups
            'node_modules',
            'scratch-backup',
            ABSPATH . 'error_log',
            WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'error_log',
            'logs',
            'node_modules',
            ABSPATH . 'lscache', //lite speed cache
            'lscache', //lite speed cache
            ABSPATH . 'umbrella-backup.php',
            ABSPATH . 'rb-plugins', // raidboxes,
            WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'nginx_cache', // nginx cache
            WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'et-cache',
            'umbrella-backup.php'
        ];
    }

    /**
     * This function is used to get the root backup module "/cloner.php"
     * It's totally different with "getDefaultSource()", use to know from where we need to start the backup directory
     */
    public function getRootBackupModule()
    {
        $host = wp_umbrella_get_service('HostResolver')->getCurrentHost();

        $source = ABSPATH;

        switch ($host) {
            case Host::FLYWHEEL:
            case Host::PRESSABLE:
                $source = untrailingslashit(WP_CONTENT_DIR) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
                break;
        }

        if (empty($source)) {
            $source = ABSPATH;
        }

        if (function_exists('apply_filters')) {
            return apply_filters('wp_umbrella_backup_root_module', $source, $host);
        }

        return $source;
    }

    public function getDefaultSource()
    {
        $host = wp_umbrella_get_service('HostResolver')->getCurrentHost();

        // Prevent Windows compatibility issues
        $source = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ABSPATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        switch ($host) {
            case Host::FLYWHEEL:
            case Host::PRESSABLE:
                $source = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, WP_CONTENT_DIR), DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . '..'
                    . DIRECTORY_SEPARATOR;
                break;

            default:
                try {
                    if (!is_dir(untrailingslashit($source) . DIRECTORY_SEPARATOR . 'wp-content')) {
                        $source = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname(ABSPATH)), DIRECTORY_SEPARATOR)
                            . DIRECTORY_SEPARATOR;
                    }

                    if (!file_exists($source . 'wp-config.php')) {
                        $source = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname(ABSPATH)), DIRECTORY_SEPARATOR)
                            . DIRECTORY_SEPARATOR;
                    }
                } catch (\Exception $e) {
                    // Do nothing
                }
                break;
        }

        if (empty($source)) {
            $source = ABSPATH;
        }

        if (function_exists('apply_filters')) {
            return apply_filters('wp_umbrella_backup_default_source', $source, $host);
        }

        return $source;
    }

    /**
     * @param $source
     * @param $excludesOption
     * @return array
     */
    public function buildAndGetExcludeFiles($source, $excludesOption): array
    {
        $excludes = $this->getDefaultExcludeFiles();
        $excludes = array_merge($excludes, $excludesOption);

        try {
            $scanAbspath = scandir($this->getDefaultSource());

            foreach ($scanAbspath as $key => $value) {
                if (!@is_dir($value)) {
                    continue;
                }

                if (in_array($value, ['.', '..', 'wp-content', 'wp-includes', 'wp-admin'])) {
                    continue;
                }

                $isOtherWP = wp_umbrella_get_service('DirectoryListing')->hasWordPressInSubfolder(realpath($value));
                if (!$isOtherWP) {
                    continue;
                }

                $excludes[] = realpath($value);
            }
        } catch (\Exception $e) {
            // No black magic
        }

        $lastCharIsSlash = substr($source, -1) === DIRECTORY_SEPARATOR;

        foreach ($excludes as $key => $value) {
            $value = str_replace($source, '', $value);

            if ($lastCharIsSlash && isset($value[0]) && $value[0] === DIRECTORY_SEPARATOR) {
                $value = \substr($value, 1);
            }

            $excludes[$key] = $value;
        }

        return $excludes;
    }

    /**
     * @param $source
     * @param $excludesOption
     * @return array
     */
    public function buildAndGetExcludeNames($source, $excludesOption): array
    {
        $defaultExcludeNames = apply_filters(
            'wp_umbrella_backup_default_exclude_names',
            ['*.mp4', '*.gz', '*.wpress', '*.mmdb', '*.mdb', '*.mov']
        );
        $excludes = array_merge($defaultExcludeNames, $excludesOption);
        return $excludes;
    }

    public function getMaxMoBatchSize()
    {
        return apply_filters('wp_umbrella_max_mo_batch_size', 120); // 120 Mo
    }

    public function getMaxMoPerFileSize()
    {
        return apply_filters('wp_umbrella_max_mo_per_file', 50); // 50 Mo
    }

    /**
     * ~13.2Mo Zip = 50 Mo Batch file size
     * Return the size by batch in bytes
     *
     * @return int
     */
    public function getMaxMoInBytesBatchSize($moSize = null)
    {
        if ($moSize === null) {
            $moSize = $this->getMaxMoBatchSize();
        }

        if (!function_exists('disk_free_space')) {
            return $moSize * self::MO_IN_BYTES;
        }

        $freeSpace = @disk_free_space(ABSPATH);
        if (!$freeSpace) {
            return $moSize * self::MO_IN_BYTES;
        }

        $freeSpace = $freeSpace / 1024 / 1024; // Mo
        if ($freeSpace > $moSize) {
            return $moSize * self::MO_IN_BYTES;
        }

        $freeSpaceDivided = $freeSpace / 2; // 50% free space

        if ($freeSpaceDivided >= $moSize) {
            return $moSize * self::MO_IN_BYTES;
        }

        return $freeSpaceDivided * self::MO_IN_BYTES;
    }

    /**
     *
     * @param array $options = [
     *		'exclude_files' => [] // string
     *		'source' => '' // source - default ABSPATH
     *		'since_date' => '', // string
     *		'size' => '', // string
     *      'exclude_names' => [] // string
     *  	'only_names' => [] // string
     * ]
     *
     * @return Finder
     */
    public function getFinder(array $options): Finder
    {
        $excludesOption = $options['exclude_files'] ?? [];
        $source = $options['source'] ?? $this->getDefaultSource();

        $excludes = $this->buildAndGetExcludeFiles($source, $excludesOption);

        $excludesNamesOption = $options['exclude_names'] ?? [];
        $excludesNames = $this->buildAndGetExcludeNames($source, $excludesNamesOption);

        $onlyNames = $options['only_names'] ?? [];

        $sinceDate = $options['since_date'] ?? null;
        $maxFileSize = $options['max_file_size'] ?? $options['size'] ?? null;

        $finder = new Finder();
        $finder->files()
                ->in($source)
                ->ignoreUnreadableDirs()
                ->ignoreDotFiles(false)
                ->exclude($excludes);

        // Exclude files by name pattern and only if not in the onlyNames array (need for select only some files)
        if (!empty($excludesNames) && empty($onlyNames)) {
            $finder->notName($excludesNames);
        }

        if (!empty($onlyNames) && is_array($onlyNames)) {
            $finder->name($onlyNames);
        }

        if ($sinceDate !== null) {
            $finder->date($sinceDate);
        }

        if ($maxFileSize !== null) {
            $finder->size($maxFileSize);
        }

        return $finder;
    }

    /**
     *
     * @param string $dir
     * @return void
     */
    protected function destroyDir($dir)
    {
        try {
            if (!is_dir($dir) || is_link($dir)) {
                return unlink($dir);
            }

            foreach (scandir($dir) as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file)) {
                    chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                    if (!$this->destroyDir($dir . DIRECTORY_SEPARATOR . $file)) {
                        return false;
                    }
                };
            }
            return rmdir($dir);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     *
     * @param array $options = [
     *		'exclude_files' => [] // string
     *		'source' => '' // source - default ABSPATH
     *		'since_date' => '', // string
     *		'size' => '', // string
     *      'exclude_names' => [] // string
     * ]
     *
     * @return int
     */
    public function countTotalFiles($options = [])
    {
        try {
            @set_time_limit(0);
            $finder = $this->getFinder($options);
            return $finder->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
