<?php

namespace WPUmbrella\Services\Repository;

use Exception;
use WPUmbrella\Core\Models\AbstractRepository;
use WPUmbrella\Models\Backup\Backup;
use WPUmbrella\Core\Constants\BackupStatus;

class BackupRepository extends AbstractRepository
{

	protected $table;

	public function __construct()
	{
		$this->table = wp_umbrella_get_service('TableList')->getTableBackup();
	}

	protected function getAuthorizedInsertValues(): array
	{
		return [
			'count_attachments',
			'count_public_posts',
			'count_plugins',
			'wp_core_version',
			'config_database',
			'config_file',
			'title',
			'suffix',
			'is_scheduled',
			'backupId',
			'incremental_date',
			'status'
		];
	}

	protected function getAuthorizedUpdateValues(): array
	{
		return [
			'config_database',
			'config_file',
			'finish_file',
			'finish_database',
			'status'
		];
	}

	public function getBackupById(int $id): ?Backup
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.id = {$id} ";

		$data = $wpdb->get_results($sql, ARRAY_A);

		$backupData = current($data);

		if ( ! $backupData) {
			return null;
		}

		return new Backup($backupData);
	}

	public function getLastBackup(): ?Backup
	{
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "ORDER BY {$alias}.id DESC ";
		$sql .= "LIMIT 1";

		$data = $wpdb->get_results($sql, ARRAY_A);

		$backupData = current($data);

		if ( ! $backupData) {
			return null;
		}

		return new Backup($backupData);
	}

	public function hasBackupInProgress(){

		$item = $this->getBackupInProgress();

		if($item === null){
			return false;
		}

		return true;
	}

	public function getBackupInProgress(){
		global $wpdb;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "WHERE {$alias}.status = %s ";

		$sql = $wpdb->prepare($sql, BackupStatus::IN_PROGRESS);

		$data = $wpdb->get_results($sql, ARRAY_A);

		$backupData = current($data);

		if ( ! $backupData) {
			return null;
		}

		return new Backup($backupData);

	}

	/**
	 * @param array $args
	 *  - offset
	 *  - limit
	 */
	public function getBackups(array $args)
	{
		global $wpdb;

		$offset = isset($args['offset']) ? $args['offset'] : 0;
		$limit  = isset($args['limit']) ? $args['limit'] : 25;

		$alias = $this->table->getAlias();

		$sql = "SELECT {$alias}.* ";
		$sql .= "FROM {$wpdb->prefix}{$this->table->getName()} {$alias} ";
		$sql .= "ORDER BY {$alias}.id DESC ";
		$sql .= "LIMIT %d, %d";

		$sql = $wpdb->prepare($sql, $offset, $limit);

		$data = $wpdb->get_results($sql, ARRAY_A);

		return array_map(
			function ($backupData) {
				return new Backup($backupData);
			},
			$data
		);
	}


	/**
	 * @param array $args
	 * - count_attachments
	 * - count_public_posts
	 * - count_plugins
	 * - wp_core_version
	 * - config_database
	 * - config_file
	 * - title
	 * - suffix
	 * - is_scheduled
	 * - backupId
	 * - incremental_date
	 */
	public function insertBackup(array $args)
	{
		global $wpdb;
		$args['status'] = BackupStatus::IN_PROGRESS;

		$sql = $this->getInsertInstruction($args);

		$sql .= $this->getInsertValuesInstruction($args);

		try {
			$wpdb->query($sql);
			return $wpdb->insert_id;
		} catch (Exception $e) {
			return null;
		}
	}


	/**
	 * @param $backupId
	 * @param array $args
	 * - config_database
	 * - config_file
	 * - in_error
	 * - finish_file
	 * - finish_database
	 */
	public function updateBackup($backupId, array $args)
	{
		global $wpdb;
		$sql = $this->getUpdateInstruction();

		$sql .= $this->getUpdateValues($args);

		$sql .= 'WHERE id = '.$backupId;

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function setBackupInError(int $backupId)
	{
		global $wpdb;
		$sql = $this->getUpdateInstruction();

		$sql .= $this->getUpdateValues([
			'in_error'        => 1,
			'status'  		  => BackupStatus::ERROR,
			'finish_file'     => true,
			'finish_database' => true,
		]);

		$sql .= 'WHERE id = '.$backupId;

		try {
			return $wpdb->query($sql);
		} catch (Exception $e) {
			return null;
		}
	}

	public function stopBackup(int $backupId)
	{

		try {
			return $this->updateBackup($backupId, [
				'status'          => BackupStatus::STOPPED,
				'finish_file'     => true,
				'finish_database' => true,
			]);
		} catch (Exception $e) {
			return null;
		}
	}

	public function setFinishByType($backupId, $type){

		$args = [];

		if($type=== 'file'){
			$args['finish_file'] = true;
		}
		else if($type=== 'database'){
			$args['finish_database'] = true;
		}

		$this->updateBackup($backupId, $args);
	}

	public function finishBackup($backupId){
		try {
			$this->updateBackup($backupId, [
				'status'          => BackupStatus::FINISHED,
			]);
		} catch (\Exception $e) {
			return null;
		}
	}
}
