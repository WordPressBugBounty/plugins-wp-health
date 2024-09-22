<?php

namespace WPUmbrella\Services\Repository;

use DateInterval;
use DateTime;
use Exception;
use WPUmbrella\Core\UmbrellaDateTime;
use WPUmbrella\Core\Constants\BackupTaskStatus;
use WPUmbrella\Core\Models\AbstractRepository;
use WPUmbrella\Models\Backup\BackupTask;

class TaskBackupRepository extends AbstractRepository
{
	protected $table;

	protected $tableBackup;

	public function __construct()
	{
		$this->table = wp_umbrella_get_service('TableList')->getTableTaskBackup();
		$this->tableBackup = wp_umbrella_get_service('TableList')->getTableBackup();
	}

	protected function getAuthorizedInsertValues(): array
	{
		return [
			"status",
			"date_schedule",
			"type",
			"log",
			"backupId",
		];
	}

	protected function getAuthorizedUpdateValues(): array
	{
		return [
			"jobId",
			"status",
			"date_start",
			"date_end",
		];
	}

	public function getLastTaskWithBackup(): ?BackupTask
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.*, {$this->tableBackup->getAlias()}.finish_file, {$this->tableBackup->getAlias()}.finish_database, {$this->tableBackup->getAlias()}.status as backup_status ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "INNER JOIN {$wpdb->prefix}{$this->tableBackup->getName()} {$this->tableBackup->getAlias()} ON {$this->tableBackup->getAlias()}.id = {$alias}.backupId ";
		$sql .= "WHERE {$alias}.status IS NOT NULL ";
		$sql .= "ORDER BY {$alias}.date_schedule DESC ";
		$sql .= "LIMIT 1";

		$data = $wpdb->get_results($sql, ARRAY_A);

		$task = current($data);

		if ( ! $task) {
			return null;
		}

		return new BackupTask($task);
	}

	public function getLastTaskByBackupId($backupId): ?BackupTask
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.backupId = %s ";
		$sql .= "ORDER BY {$alias}.date_schedule DESC ";
		$sql .= "LIMIT 1";

		$sql = $wpdb->prepare($sql, $backupId);

		$data = $wpdb->get_results($sql, ARRAY_A);

		$task = current($data);

		if ( ! $task) {
			return null;
		}

		return new BackupTask($task);
	}

	public function getNextTask(): ?BackupTask
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.jobId IS NULL ";
		$sql .= "AND {$alias}.status IS NULL ";
		$sql .= "ORDER BY {$alias}.date_schedule ASC ";
		$sql .= "LIMIT 1";

		$data = $wpdb->get_results($sql, ARRAY_A);

		$task = current($data);

		if ( ! $task) {
			return null;
		}

		return new BackupTask($task);
	}

	public function getNextTaskByBackupId($backupId): ?BackupTask
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.jobId IS NULL ";
		$sql .= "AND {$alias}.status IS NULL ";
		$sql .= "AND {$alias}.backupId = %s ";
		$sql .= "ORDER BY {$alias}.date_schedule ASC ";
		$sql .= "LIMIT 1";

		$sql = $wpdb->prepare($sql, $backupId);

		$data = $wpdb->get_results($sql, ARRAY_A);

		$task = current($data);

		if ( ! $task) {
			return null;
		}

		return new BackupTask($task);
	}

	public function findById(int $id): ?BackupTask
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.id = %s ";
		$sql .= "LIMIT 1";

		$sql = $wpdb->prepare($sql, $id);
		$data = $wpdb->get_results($sql, ARRAY_A);

		$task = current($data);

		if ( ! $task) {
			return null;
		}

		return new BackupTask($task);
	}

	protected function getNextJobId(): int
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT MAX({$alias}.jobId) ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";

		$data = $wpdb->get_var($sql);

		if (empty($data)) {
			return 1;
		}

		return (int)$data + 1;
	}

	/**
	 * @return BackupTask[]
	 */
	public function getPendingTasks(): array
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.jobId IS NULL ";

		$data = $wpdb->get_results($sql, ARRAY_A);

		return array_map(function ($taskData) {
			return new BackupTask($taskData);
		}, $data);
	}

	/**
	 * @return BackupTask[]
	 */
	public function getTasksInProgress(): array
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.status = '".BackupTaskStatus::IN_PROGRESS."' ";

		$data = $wpdb->get_results($sql, ARRAY_A);

		return array_map(function ($taskData) {
			return new BackupTask($taskData);
		}, $data);
	}


	public function hasTaskInProgressByTypes(array $types): bool
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.status = '".BackupTaskStatus::IN_PROGRESS."' ";
		$sql .= "AND {$alias}.type IN (".implode(',', array_fill(0, count($types), '%s')).") ";

		$data = $wpdb->get_results($sql);

		return ! empty($data);
	}

	public function hasAtLeastOneTaskInProgress(): bool
	{
		$data = $this->getTasksInProgress();

		return ! empty($data);
	}

	public function hasPendingTask(): bool
	{
		$data = $this->getPendingTasks();

		return ! empty($data);
	}

	public function deleteBackupById(int $id)
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "DELETE FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.id = '".$id."' ";

		try {
			$wpdb->query($sql);
		} catch (\Exception $e) {

		}
	}

	public function getFirstTaskByBackupId(int $backupId): ?BackupTask
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.backupId = '".$backupId."' ";
		$sql .= "ORDER BY {$alias}.date_schedule ASC ";
		$sql .= "LIMIT 1";

		$data = $wpdb->get_results($sql, ARRAY_A);

		$task = current($data);

		if ( ! $task) {
			return null;
		}

		return new BackupTask($task);
	}

	/**
	 * @param int $backupId
	 *
	 * @return BackupTask[]
	 */
	public function getTasksByBackupId(int $backupId): array
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.backupId = '".$backupId."' ";

		$data = $wpdb->get_results($sql, ARRAY_A);

		return array_map(function ($taskData) {
			return new BackupTask($taskData);
		}, $data);
	}

	/**
	 * @param array $args
	 *        - type
	 *        - log
	 *        - backupId
	 */
	public function insertTask(array $args)
	{
		global $wpdb;

		$args['date_schedule'] = (new UmbrellaDateTime('now'))->add(new DateInterval('PT60S'));

		$sql = $this->getInsertInstruction($args);

		$sql .= $this->getInsertValuesInstruction($args);

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function setStartTask(int $taskId)
	{
		$jobId = $this->getNextJobId();

		global $wpdb;
		$sql = $this->getUpdateInstruction();

		$sql .= $this->getUpdateValues([
			"jobId"      => $jobId,
			"status"     => BackupTaskStatus::IN_PROGRESS,
			"date_start" => new UmbrellaDateTime(),
		]);

		$sql .= 'WHERE id = '.$taskId;

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function setSuccessTask($taskId)
	{
		global $wpdb;
		$sql = $this->getUpdateInstruction();

		$sql .= $this->getUpdateValues([
			"status"   => BackupTaskStatus::SUCCESS,
			"date_end" => new UmbrellaDateTime(),
		]);

		$sql .= 'WHERE id = '.$taskId;

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function setErrorTask($taskId)
	{
		global $wpdb;
		$sql = $this->getUpdateInstruction();

		$sql .= $this->getUpdateValues([
			"status"   => BackupTaskStatus::ERROR,
			"date_end" => new UmbrellaDateTime(),
		]);

		$sql .= 'WHERE id = '.$taskId;

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function setStoppedTasksByBackupId(int $backupId)
	{
		global $wpdb;
		$sql = $this->getUpdateInstruction();

		$sql .= $this->getUpdateValues([
			"status"   => BackupTaskStatus::STOPPED,
			"date_end" => new UmbrellaDateTime(),
		]);

		$sql .= 'WHERE backupId = '.$backupId;
		$sql .= ' AND ( status = "'.BackupTaskStatus::IN_PROGRESS.'" OR status IS NULL ) ';

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function deleteTaskBackupsBeforeDate(DateTime $date){
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "DELETE FROM {$wpdb->prefix}{$this->table->getName()} ";
		$sql .= "WHERE date_schedule <  %s ";

		$sql = $wpdb->prepare($sql, $date->format('Y-m-d H:i:s'));

		try {
			$wpdb->query($sql);
		} catch (\Exception $e) {

		}
	}

}
