<?php

namespace WPUmbrella\Services\Repository;

use Exception;
use DateTime;
use WPUmbrella\Core\Models\AbstractRepository;
use WPUmbrella\Services\Table\TableList;

class LogRepository extends AbstractRepository
{

	protected $table;

	public function __construct()
	{
		/** @var TableList $tableList */
		$tableList = wp_umbrella_get_service('TableList');
		$this->table = $tableList->getTableLog();
	}

	protected function getAuthorizedInsertValues(): array
	{
		return [
			"code",
			"message",
			"backupId",
		];
	}

	protected function getAuthorizedUpdateValues(): array
	{
		return [];
	}

	/**
	 * @param array $args
	 * - code
	 * - message
	 * - backupId
	 */
	public function insertLog(array $args) {
		global $wpdb;
		$sql = $this->getInsertInstruction($args);

		$sql .= $this->getInsertValuesInstruction($args);

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function insertLogs(array $args) {
		global $wpdb;
		$firstElement = current($args);

		$sql = $this->getInsertInstruction($firstElement);

		foreach( $args as $log){
			$values[] = $this->getInsertValuesInstruction($log);
		}
		$sql .= implode(', ', $values);

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function deleteLogsByBackupId(int $backupId): bool
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "DELETE FROM {$wpdb->prefix}{$this->table->getName()} ";
		$sql .= "WHERE backupId = %d ";

		$sql = $wpdb->prepare($sql, $backupId);

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return false;
		}
	}

	public function getLogsByBackupId(int $backupId): array
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.backupId = {$backupId} ";
		$sql .= "ORDER BY {$alias}.id ASC ";

		$data = $wpdb->get_results($sql);

		return $data;
	}


	public function deleteLogsBeforeDate(DateTime $date)
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "DELETE FROM {$wpdb->prefix}{$this->table->getName()} ";
		$sql .= "WHERE created_at < %s ";

		$sql = $wpdb->prepare($sql, $date->format('Y-m-d H:i:s'));

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return false;
		}
	}
}
