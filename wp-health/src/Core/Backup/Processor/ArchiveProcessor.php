<?php

namespace WPUmbrella\Core\Backup\Processor;

use WPUmbrellaVendor\Symfony\Component\Process\Process;
use WPUmbrella\Models\Backup\BackupProcessCommandLine;
use WPUmbrella\Models\Backup\BackupProcessor;


abstract class ArchiveProcessor implements BackupProcessor, BackupProcessCommandLine
{
    const DEFAULT_TIMEOUT = 900;

    private $namer;
    private $command;
    private $options;
    private $extension;
    private $timeout;

    /**
     * @param string $namer
     * @param string $command
     * @param string $options
     * @param string $extension
     * @param int    $timeout
     */
    public function __construct($namer, $command, $options, $extension, $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->namer = $namer;
        $this->command = $command;
        $this->options = $options;
        $this->extension = $extension;
        $this->timeout = $timeout;
    }

	public function getTimeout(){
		return apply_filters('wp_umbrella_backup_processor_timeout', $this->timeout);
	}

	public function canExecute(){
		return null;
	}

	public function getCommandLine(){
		return $this->command;
	}

	public function getExtension(){
		return $this->extension;
	}

    /**
     * {@inheritdoc}
     */
    public function process($scratchDir, $dirDestinationZip = '')
    {
		if(empty($dirDestinationZip)){
			$dirDestinationZip = \sys_get_temp_dir();
		}

        $filename = sprintf('%s%s.%s', $dirDestinationZip, $this->getName(), $this->getExtension());

        if(!function_exists('proc_open')){
			return $filename;
		}

		$process = new Process([$this->getCommandLine(), $this->options, $filename, './'], $scratchDir, null, null, $this->getTimeout());

		$process->run();

		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}

        return $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup($filename)
    {

		wp_umbrella_remove_file($filename);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->namer->getName();
    }
}
