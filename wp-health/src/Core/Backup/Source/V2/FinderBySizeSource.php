<?php
namespace WPUmbrella\Core\Backup\Source\V2;

use WPUmbrella\Models\Backup\BackupSource;
use WPUmbrella\Core\Iterator\LimitBySizeIterator;
use WPUmbrella\Models\Backup\BackupProcessCommandLine;
use WPUmbrella\Helpers\DataTemporary;
use ZipArchive;

class FinderBySizeSource implements BackupSource, BackupProcessCommandLine
{
    const DEFAULT_TIMEOUT = 900;

    protected $name;
    protected $source;
    protected $sinceDate = null;
    protected $size = null;
    protected $timeout;

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
		$this->version = 'v1';
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

    public function setSinceDate($sinceDate)
    {
        $this->sinceDate = $sinceDate;
        return $this;
    }

    public function getCommandLine()
    {
        return null;
    }

	public function setVersion($version){
		$this->version = $version;
		return $this;
	}

    public function fetch($scratchDir)
    {

        if($this->version === 'v1'){
			$dataObj = wp_umbrella_get_service('BackupManageProcess')->getBackupData();
		}
		else if($this->version === 'v3'){
			$dataObj = wp_umbrella_get_service('BackupManageProcessCustomTable')->getBackupData();
		}

        $finder = wp_umbrella_get_service('BackupFinderConfiguration')->getFinder([
            'source' => $this->source,
            'since_date' => $this->sinceDate,
            'size' => $this->size,
            'exclude_files' => $dataObj->getExcludeFiles(),
        ]);

        $countIterator = $dataObj->getBatchIterator('file');

        $iterator = new LimitBySizeIterator($finder->getIterator(), $countIterator);
        $iterator->setMaxSize($dataObj->getMaxSize());

        $fileZip = sprintf('%s/%s.zip', $scratchDir, $this->getName());

		$i = 0;
        try {
            $zip = new ZipArchive();
            $zip->open($fileZip, ZipArchive::CREATE);

            foreach ($iterator as $file) {
				if($file === null) {
					continue;
				}

                $realPath = $file->getRealPath();
                if (!\file_exists($realPath)) {
                    continue;
                }
                if (\strpos($realPath, '.DS_Store') !== false) {
                    continue;
                }
				$i++;
                $zip->addFile($file->getRealPath(), $file->getRelativePathname());
            }

            @$zip->close();

        } catch (\Exception $e) {
			wp_umbrella_get_service('TaskBackupLogger')->error("[FinderBySizeSource] {$e->getMessage()}", $dataObj->getUmbrellaBackupId());

			$handle = wp_umbrella_get_service('PreventErrorOnPathNotAllowed')->execute($e);

			if(!$handle){
				DataTemporary::setDataByKey('message_error_backup', $e->getMessage());
			}

			do_action('wp_umbrella_backup_finder_by_size_source_error', $e);

            return [
                'success' => false,
                'iterator_position' => $iterator->getPosition(),
				'count_iterator' => $i,
                'processed_data' => $dataObj
            ];
        }

		try {
			if($i === 0) {
				wp_umbrella_remove_file($fileZip);
			}
		} catch (\Exception $e) {
			wp_umbrella_get_service('TaskBackupLogger')->error("[FinderBySizeSource] {$e->getMessage()}", $dataObj->getUmbrellaBackupId());
			// do nothing
		}

        return [
            'success' => true,
            'iterator_position' => $iterator->getPosition(),
			'count_iterator' => $i,
            'processed_data' => $dataObj
        ];
    }

    public function getName()
    {
        return $this->namer->getName();
    }
}
