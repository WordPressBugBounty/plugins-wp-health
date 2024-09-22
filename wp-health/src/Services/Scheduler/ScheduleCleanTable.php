<?php

namespace WPUmbrella\Services\Scheduler;

use WPUmbrella\Core\UmbrellaDateTime;
use WPUmbrella\Core\Scheduler\MemoryLimit;
use WPUmbrella\Core\Scheduler\TimeLimit;
use WPUmbrella\Models\Backup\BackupTask;
use WPUmbrella\Services\Api\Backup;
use WPUmbrella\Services\Repository\TaskBackupRepository;
use WPUmbrella\Services\Repository\LogRepository;

class ScheduleCleanTable implements Scheduler
{
	use TimeLimit;
	use MemoryLimit;

	/**
	 * @var TaskBackupRepository
	 */
	protected $taskBackupRepository;

	/**
	 * @var LogRepository
	 */
	protected $logRepository;

	public function __construct()
	{
		$this->taskBackupRepository = wp_umbrella_get_service('TaskBackupRepository');
		$this->logRepository = wp_umbrella_get_service('LogRepository');
	}

	public function isAllowed(): bool
	{
		return ! $this->memoryExceeded();
	}

	public function execute()
	{

		$date = new \DateTime('now');
		$date->sub(new \DateInterval('P14D'));

		$date = apply_filters('wp_umbrella_scheduler_clean_table_date', $date);


		try {
			$this->taskBackupRepository->deleteTaskBackupsBeforeDate($date);
			$this->logRepository->deleteLogsBeforeDate($date);
		} catch (\Exception $e) {
			//No need to do anything
		}
	}

}
