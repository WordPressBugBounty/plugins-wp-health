<?php
namespace WPUmbrella\Core\Backup\Source;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupSource;
use WPUmbrella\Models\Backup\BackupProcessCommandLine;
use ZipArchive;

class FinderByFileSource implements BackupSource, BackupProcessCommandLine
{
    const DEFAULT_TIMEOUT = 900;

    private $name;
    private $source;
    private $sinceDate = null;
    private $size = null;
    private $timeout;

    /**
     * @param Namer $name
     * @param string $source            The rsync source
     * @param int    $timeout
     */
    public function __construct($namer, $source, $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->namer = $namer;
        $this->source = $source;
        $this->timeout = $timeout;
    }

    public function getTimeout()
    {
        return apply_filters('wp_umbrella_backup_source_finder_batch_timeout', $this->timeout);
    }

    public function setSinceDate($sinceDate)
    {
        $this->sinceDate = $sinceDate;
        return $this;
    }

    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

    public function getCommandLine()
    {
        return null;
    }

    public function fetch($scratchDir)
    {
        $dataObj = wp_umbrella_get_service('BackupBatchData')->getData('file');

        $finder = wp_umbrella_get_service('BackupFinderConfiguration')->getFinder([
            'source' => $this->source,
            'since_date' => $this->sinceDate,
            'size' => $this->size,
            'exclude_files' => $dataObj->getExcludeFiles(),
        ]);

        $current = $dataObj->getCurrentBatchProcessor();

        $maxFilesByBatch = $dataObj->getMaxFilesByBatch();
        $iterator = new \LimitIterator($finder->getIterator(), $current, $maxFilesByBatch);

        $fileZip = sprintf('%s/%s.zip', $scratchDir, $this->getName());

        try {
            $zip = new ZipArchive();
            $zip->open($fileZip, ZipArchive::CREATE);

            foreach ($iterator as $file) {
                $realPath = $file->getRealPath();
                if (!\file_exists($realPath)) {
                    continue;
                }
                if (\strpos($realPath, '.DS_Store') !== false) {
                    continue;
                }

                $zip->addFile($file->getRealPath(), $file->getRelativePathname());
            }

            $zip->close();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'iterator_position' => $iterator->getPosition(),
            ];
        }

        return [
            'success' => true,
            'iterator_position' => $iterator->getPosition(),
        ];
    }

    public function getName()
    {
        return $this->namer->getName();
    }
}
