<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class EmptiedFileAnalyzer
{
    const MAX_ENTRIES = 50;

    // We ship around 400 files. The ceiling is there so the walk stays bounded
    // whatever ends up sitting in those directories, not to trim a normal scan.
    const MAX_SCANNED_FILES = 2000;

    const SCANNED_DIRECTORIES = ['src', 'request', 'backup-script'];

    public function analyze()
    {
        if (!defined('WP_UMBRELLA_DIR')) {
            return null;
        }

        $root = rtrim(WP_UMBRELLA_DIR, DIRECTORY_SEPARATOR);
        $checked = 0;
        $emptiedCount = 0;
        $unreadableCount = 0;
        $emptied = [];
        $unreadable = [];

        foreach ($this->files($root) as $path) {
            ++$checked;

            $size = $this->sizeOf($path);

            // A size we could not read is not a size of zero. filesize() also
            // returns false when the file just vanished or when open_basedir
            // denies it, and calling that "emptied" would cry wolf exactly
            // while an antivirus is busy working through our directory.
            if ($size === false) {
                ++$unreadableCount;
                $this->collect($unreadable, $root, $path);

                continue;
            }

            if ($size > 0) {
                continue;
            }

            ++$emptiedCount;
            $this->collect($emptied, $root, $path);
        }

        return [
            'checked' => $checked,
            // Counted apart from the listing: the lists are capped, the counts
            // are not, so a wide sweep still reports its real size.
            'emptied_count' => $emptiedCount,
            'emptied' => $emptied,
            'unreadable_count' => $unreadableCount,
            'unreadable' => $unreadable,
            // A directory that disappeared as a whole leaves nothing to walk,
            // which would otherwise read as a clean scan.
            'missing_directories' => $this->missingDirectories($root),
            'truncated' => $checked >= self::MAX_SCANNED_FILES,
        ];
    }

    /**
     * @return int|false
     */
    protected function sizeOf($path)
    {
        try {
            return @filesize($path);
        } catch (\Throwable $e) {
            // A site turning warnings into exceptions must not lose the whole
            // scan over one file.
            return false;
        }
    }

    protected function collect(&$list, $root, $path)
    {
        if (count($list) >= self::MAX_ENTRIES) {
            return;
        }

        $list[] = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    }

    /**
     * @return string[]
     */
    protected function missingDirectories($root)
    {
        $missing = [];

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            if (!is_dir($root . DIRECTORY_SEPARATOR . $directory)) {
                $missing[] = $directory;
            }
        }

        return $missing;
    }

    /**
     * @return string[]
     */
    protected function files($root)
    {
        $paths = [];

        foreach (glob($root . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
            if (is_link($path)) {
                continue;
            }

            $paths[] = $path;
        }

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $remaining = self::MAX_SCANNED_FILES - count($paths);

            if ($remaining < 1) {
                break;
            }

            $paths = array_merge(
                $paths,
                $this->filesIn($root . DIRECTORY_SEPARATOR . $directory, $remaining)
            );
        }

        return $paths;
    }

    /**
     * @param int $limit
     *
     * @return string[]
     */
    protected function filesIn($directory, $limit)
    {
        if (!is_dir($directory)) {
            return [];
        }

        $paths = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (count($paths) >= $limit) {
                break;
            }

            // A symlink is skipped rather than resolved: isFile() and filesize()
            // both follow it, so a link pointing at an empty file outside the
            // plugin would be reported as one of ours.
            if (!$file->isFile() || $file->isLink() || $file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        return $paths;
    }
}
