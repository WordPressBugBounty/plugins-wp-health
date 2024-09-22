<?php

namespace WPUmbrella\Services\Scheduler;

use WPUmbrella\Models\Backup\BackupTask;

interface Scheduler
{
	public function execute();

	public function timeExceeded(BackupTask $task): bool;

	public function memoryExceeded(): bool;

}
