<?php

namespace WPUmbrella\Models\Backup;


interface BackupProcessor
{
	public function process($scratchDir, $dirDestinationZip = '');
	public function canExecute();
}
