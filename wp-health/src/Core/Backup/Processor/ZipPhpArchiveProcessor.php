<?php

namespace WPUmbrella\Core\Backup\Processor;

use WPUmbrella\Models\Backup\BackupProcessor;
use ZipArchive;

class ZipPhpArchiveProcessor implements BackupProcessor
{
    const DEFAULT_TIMEOUT = 900;

    private $namer;
    private $command;
    private $options;
    private $extension;
    private $timeout;

    /**
     * @param BackupNamer $namer
     * @param string $command
     * @param string $options
     * @param string $extension
     * @param int    $timeout
     */
    public function __construct($namer, $command = null, $options = '', $extension = 'zip', $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->namer = $namer;
        $this->command = $command;
        $this->options = $options;
        $this->extension = $extension;
        $this->timeout = $timeout;
    }

	public function getTimeout(){
		return $this->timeout;
	}

	public function getExtension(){
		return $this->extension;
	}

	public function canExecute(){
		return class_exists('ZipArchive');
	}

    /**
     * {@inheritdoc}
     */
    public function process($scratchDir, $dirDestinationZip = '')
    {
		$filename = sprintf('%s/%s.%s', $scratchDir, $this->getName(), $this->getExtension());


		if(!$this->canExecute()){
			return $filename;
		}


		$zip = new ZipArchive;
		$zip->open($filename, ZipArchive::CREATE);

		$source = sprintf('%s/%s', $scratchDir, $this->getName());
		$source = str_replace('\\', '/', realpath($source));


		if(empty($source)){
			return $filename;
		}

		if (is_dir($source) === true)
		{
			$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

			foreach ($files as $file)
			{
				$file = str_replace('\\', '/', $file);

				@set_time_limit($this->getTimeout());

				// Ignore "." and ".." folders
				if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) ){
					continue;
				}

				$file = realpath($file);

				if (is_dir($file) === true)
				{
					$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
				}
				else if (is_file($file) === true)
				{
					$zip->addFile($file, str_replace($source . '/', '', $file));
				}
			}
		}
		else if (is_file($source) === true)
		{
			$zip->addFile($source, basename($source));
		}

		$zip->close();

        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup($filename)
    {
		wp_umbrella_remove_file($filename);
    }

	public function getFilename($dirDestinationZip = ''){
		if(empty($dirDestinationZip)){
			$dirDestinationZip = \sys_get_temp_dir();
		}

		return sprintf('%s%s.%s', $dirDestinationZip, $this->getName(), $this->getExtension());
	}

    /**
     * @return string
     */
    public function getName()
    {
        return $this->namer->getName();
    }
}
