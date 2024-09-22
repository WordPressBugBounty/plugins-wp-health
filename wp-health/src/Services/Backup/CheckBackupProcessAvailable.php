<?php

namespace WPUmbrella\Services\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrellaVendor\Symfony\Component\Process\Process;
use WPUmbrella\Models\Backup\BackupProcessCommandLine;
use WPUmbrella\Models\Backup\BackupProcessor;

class CheckBackupProcessAvailable
{

	/**
	 *
	 * @param string $cli
	 * @return boolean
	 */
	protected function canExecuteCommandLine($cli = null){

		if($cli === null){
			return true;
		}

		if(!function_exists('proc_open')){
			return false;
		}

		$process = new Process([$cli, "--help"]);
		$process->run();

		if(!empty($process->getErrorOutput())){
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param BackupProcessor $processor
	 * @return boolean
	 */
	public function canExecuteProcessor(BackupProcessor $processor)
	{
		$result = $processor->canExecute();
		if($result !== null){
			return $result;
		}

		$cli = $processor->getCommandLine();

		return $this->canExecuteCommandLine($cli);
	}

	/**
	 *
	 * @param BackupProcessCommandLine $source
	 * @return boolean
	 */
    public function canExecuteSource(BackupProcessCommandLine $source)
    {
		$cli = $source->getCommandLine();

		return $this->canExecuteCommandLine($cli);
    }

}
