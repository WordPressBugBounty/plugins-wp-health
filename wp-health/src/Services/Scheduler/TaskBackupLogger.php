<?php

namespace WPUmbrella\Services\Scheduler;

use DateTime;
use WPUmbrella\Core\Constants\LogCode;
use WPUmbrella\Helpers\DataTemporary;
use WPUmbrella\Models\Backup\BackupTask;
use WPUmbrella\Services\Api\Backup;
use WPUmbrella\Services\Repository\LogRepository;
use WPUmbrella\Services\Repository\TaskBackupRepository;

class TaskBackupLogger
{
	/**
	 * @var DataTemporary
	 */
	protected $temporary;

	/**
	 * @var LogRepository
	 */
	protected $repository;

	/**
	 * @var int|null
	 */
	protected $backupId = null;

	function __construct()
	{
		$this->repository = wp_umbrella_get_service('LogRepository');
	}

	public function clear()
	{
		DataTemporary::setDataByKey('logs', []);
	}

	public function setBackupId(int $backupId)
	{
		$this->backupId = $backupId;
	}

	public function getBackupId(): ?int
	{
		return $this->backupId;
	}

	protected function log(string $message, string $type, $backupId = null)
	{
		$message = [
			'code'     => $type,
			'message'  => $message,
			'backupId' => $backupId ?? $this->getBackupId(),
		];

		$logs = DataTemporary::getDataByKey('logs');
		if ( ! is_array($logs)) {
			$logs = [];
		}

		$logs[] = $message;
		DataTemporary::setDataByKey('logs', $logs);
	}

	public function error(string $message, $backupId= null)
	{
		$this->log($message, LogCode::ERROR, $backupId);
	}

	public function info(string $message, $backupId= null)
	{
		$this->log($message, LogCode::INFO, $backupId);
	}

	public function warn(string $message, $backupId= null)
	{
		$this->log($message, LogCode::WARN, $backupId);
	}

	public function success(string $message, $backupId= null)
	{
		$this->log($message, LogCode::SUCCESS, $backupId);
	}

	public function getLogs(): array
	{
		return DataTemporary::getDataByKey('logs');
	}

	public function save()
	{
		$this->repository->insertLogs($this->getLogs());
	}
}
