<?php

namespace WPUmbrella\Services\Scheduler;

use Exception;
use WPUmbrella\Core\Constants\BackupTaskType;
use WPUmbrella\Core\Constants\SchedulerLoggerCode;
use WPUmbrella\Core\Constants\BackupTaskStatus;
use WPUmbrella\Core\Scheduler\MemoryLimit;
use WPUmbrella\Core\Scheduler\TimeLimit;
use WPUmbrella\Models\Backup\BackupTask;
use WPUmbrella\Services\Api\Backup;
use WPUmbrella\Services\Repository\TaskBackupRepository;
use WPUmbrella\Core\Constants\CodeResponse;
use WPUmbrella\Core\Constants\BackupStatus;
use WPUmbrella\Helpers\DataTemporary;

class ScheduleTaskBackup implements Scheduler
{
	use TimeLimit;
	use MemoryLimit;

	/**
	 * @var TaskBackupRepository
	 */
	protected $taskBackupRepository;


	protected $backupRepository;

	/**
	 * @var BackupTask
	 */
	protected $currentTask;

	/**
	 * @var Backup
	 */
	protected $backupApi;

	/**
	 * @var TaskBackupLogger
	 */
	protected $logger;

	protected $startMemoryUsage;

	public function __construct()
	{
		$this->taskBackupRepository = wp_umbrella_get_service('TaskBackupRepository');
		$this->backupRepository 	= wp_umbrella_get_service('BackupRepository');
		$this->backupApi            = wp_umbrella_get_service('Backup');
		$this->logger               = wp_umbrella_get_service('TaskBackupLogger');
	}

	public function isAllowed(): bool
	{
		return ! $this->memoryExceeded() && ! $this->taskBackupRepository->hasAtLeastOneTaskInProgress();
	}

	public function execute()
	{
		$nextTask = $this->taskBackupRepository->getNextTask();

		if (is_null($nextTask)) {

			$backupInProgress = wp_umbrella_get_service('BackupRepository')->getBackupInProgress();
			if(!$backupInProgress){
				return;
			}

			$lastTask = wp_umbrella_get_service('TaskBackupRepository')->getLastTaskByBackupId($backupInProgress->getId());
			$endTask = $lastTask->getDateEnd();

			$interval = new \DateInterval('PT20H'); // 20 hours
			$endTask->add($interval);

			$now = new \DateTime();

			// Compare les dates
			if($now > $endTask){
				$this->logger->clear();
				$this->logger->setBackupId($lastTask->getBackupId());
				$this->logger->info('Task blocked itself');
				$this->logger->warn('Stop running backup');
				$this->logger->save();

				$this->taskBackupRepository->insertTask([
					"type" => $lastTask->getType(),
					"backupId" => $lastTask->getBackupId()
				]);

				$this->backupRepository->stopBackup($lastTask->getBackupId());
				$this->taskBackupRepository->setStoppedTasksByBackupId($lastTask->getBackupId());
			}

			return;
		}

		add_filter('wp_fatal_error_handler_enabled', '__return_false');

		$this->logger->clear();

		$this->runTask($nextTask);
	}

	protected function convertMemorySize($size)
	{
	   	try {
			$unit = array('b','kb','mb','gb','tb','pb');

			$dividedBy = pow(1024,($i=floor(log($size,1024))));

			if((int) $dividedBy === 0){
				return $size;
			}

	   		return round(
				$size / $dividedBy, 2
			).' '.$unit[$i];
	   	} catch (\Exception $e) {
			return $size;
	   	}
	}

	public function runTask(BackupTask $task){
		$this->startTime = microtime(true);

		$this->currentTask = $task;
		$this->logger->setBackupId($this->currentTask->getBackupId());
		$this->logger->info('Run backup task #'.$this->currentTask->getId());

		if ($this->timeExceeded($this->currentTask)) {
			$this->logger->info('Time exceeded');
			$this->logger->info('Execution time: '.$this->getExecutionTime($task));
			$this->logger->info('Time limit: '.$this->getTimeLimit());
			$this->logger->warn('Stop running backup task');
			$this->logger->save();

			return;
		}

		if ($this->taskBackupRepository->hasAtLeastOneTaskInProgress()) {
			$this->logger->info('Already has backup task in progress');
			$this->logger->warn('Stop running backup task');
			$this->logger->save();
			return;
		}

		$this->startMemoryUsage = null;
		if(function_exists('memory_get_usage')){
			$this->startMemoryUsage = memory_get_usage();
			$this->logger->info("Start Memory usage: " . $this->convertMemorySize($this->startMemoryUsage));
		}

		add_action('shutdown', [$this, 'handleUnexpectedShutdown']);

		try {
			$this->logger->info('Change backup task status to '.BackupTaskStatus::IN_PROGRESS);
			$this->taskBackupRepository->setStartTask($this->currentTask->getId());

			$code = $this->runBackupByType($this->currentTask);
			$this->logger->info('Change backup task status to '.BackupTaskStatus::SUCCESS);

			if($code === CodeResponse::SUCCESS){
				$this->taskBackupRepository->setSuccessTask($this->currentTask->getId());
			}
			else{
				$this->setBackupInError();
			}


		} catch (Exception $e) {
			$this->logger->info('Handle backup task exception');
			$this->handleError($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
		}

		$endTime = microtime(true);
		$this->logger->info('Backup task took '.($endTime - $this->startTime).' seconds');

		if(($endTime - $this->startTime) >= 20 ){
			$this->logger->warn('Backup task took more than 20 seconds');
		}

		if(function_exists('memory_get_usage')){
			$memory = memory_get_usage();
			$this->logger->info("End Memory usage: " . $this->convertMemorySize($memory));
			$this->logger->info("Total Memory usage by backup task: " . $this->convertMemorySize($memory - $this->startMemoryUsage));
		}

		$this->logger->save();
		remove_action('shutdown', [$this, 'handleUnexpectedShutdown']);
	}

	public function setBackupInError(){
		$this->logger->error('Backup in error');

		$data = wp_umbrella_get_service('BackupManageProcessCustomTable')->getBackupData();

		$this->taskBackupRepository->setErrorTask($this->currentTask->getId());
		$this->backupRepository->setBackupInError($this->currentTask->getBackupId());

		$dataPost = $data->getData();
		$dataPost['code_error'] = DataTemporary::getDataByKey('code_error_backup');
		$dataPost['message_error_backup'] = DataTemporary::getDataByKey('message_error_backup');

		wp_umbrella_get_service('BackupApi')->postErrorBackup(
			$dataPost
		);

	}

	public function handleUnexpectedShutdown()
	{
		$error = error_get_last();
		if (is_null($error)) {
			return;
		}
		if ( ! in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
			return;
		}

		$this->logger->info('Handle unexpected shutdown');
		$this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
	}

	public function handleError($code, $message, $file, $line, $ctx = [])
	{
		$this->logger->info('Backup task in error');
		$this->logger->warn('Message: '.$message);
		$this->setBackupInError();
		$endTime = microtime(true);
		$this->logger->info('Backup task took '.($endTime - $this->startTime).' seconds');
		$this->logger->save();
	}

	/**
	 * @throws Exception
	 */
	public function runBackupByType(BackupTask $task)
	{

		if(!defined('WP_UMBRELLA_INIT_BACKUP')){
			define('WP_UMBRELLA_INIT_BACKUP', true);
		}

		$this->logger->info('Run backup "'.$task->getType().'"');

		switch ($task->getType()) {
			case BackupTaskType::BACKUP_DATABASE:
				return wp_umbrella_get_service('BackupActionQueueRunnerDatabase')->handle([
					"version" => "v3",
				]);
				break;
			case BackupTaskType::BACKUP_FILES:
				return wp_umbrella_get_service('BackupActionQueueRunnerFile')->handle([
					"version" => "v3",
				]);
				break;
			case BackupTaskType::BACKUP_PREPARE_BATCH_DATABASE:
				return wp_umbrella_get_service('BackupActionQueueRunnerPrepareBatchDatabase')->handle([
					"version" => "v3",
				]);
				break;
			case BackupTaskType::BACKUP_TABLE_CHECK_BATCH:
				return wp_umbrella_get_service('BackupActionQueueRunnerCheckBatchDatabase')->handle([
					"version" => "v3",
				]);
				break;
			case BackupTaskType::BACKUP_NEED_CLEANUP:
				wp_umbrella_get_service('BackupManageProcessCustomTable')->deleteProcess();
				wp_umbrella_get_service('BackupExecutorV2')->cleanup();
				return CodeResponse::SUCCESS;
				break;
			default:
				throw new Exception('No task type found');
		}
	}
}
