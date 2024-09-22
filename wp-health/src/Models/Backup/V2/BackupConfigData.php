<?php

namespace WPUmbrella\Models\Backup\V2;

use WPUmbrella\Models\Backup\Backup;

class BackupConfigData
{

	protected $data;

	public function __construct($data)
	{
		$this->version = 'v1';
		$this->data = $data;
		$this->data['umbrella_backup_id'] = null;
	}

	public function setFromBackup(Backup $backup)
	{
		$this->version = 'v3';
		$this->data['umbrella_backup_id'] = $backup->getId();
		$this->data['title']            = $backup->getTitle();
		$this->data['backupId']         = $backup->getBackupId();
		$this->data['suffix']           = $backup->getSuffix();
		$this->data['incremental_date'] = $backup->getIncrementalDate();
		$this->data['file']             = $backup->getConfigFile();
		$this->data['database']         = $backup->getConfigDatabase();
	}

	public function getVersion()
	{
		return $this->version;
	}

	public function getUmbrellaBackupId(){
		return $this->data['umbrella_backup_id'];
	}

	public function getData()
	{
		return $this->data;
	}

	public function getTitle()
	{
		return $this->data['title'];
	}

	public function getSuffix()
	{
		return $this->data['suffix'];
	}

	/**
	 * @param string $name
	 * @param string $type (file|database)
	 */
	public function setName($name, $type)
	{
		if ($type === 'file') {
			$this->data['file']['name'] = $name;
		}

		if ($type === 'database') {
			$this->data['database']['name'] = $name;
		}

		return $this;
	}

	public function setTimestampEndDate($value)
	{
		$this->data['timestamp_end_date'] = $value;

		return $this;
	}

	public function getDatabaseCurrentChecksum(){
		return $this->data['database']['checksum']['current'];
	}

	public function getDatabaseFromChecksum(){
		return $this->data['database']['checksum']['from'];
	}

	public function getMaximumMemoryAuthorized()
	{
		return $this->data['database']['batch']['maximum_memory_authorized'];
	}

	public function getBaseDirectory()
	{
		return $this->data['file']['base_directory'];
	}

	public function getIncrementalDate()
	{
		return $this->data['incremental_date'];
	}

	public function isIncremental(){
		return $this->data['incremental_date'] !== null;
	}

	public function getBatchSize()
	{
		return $this->data['file']['batch']['size'];
	}

	public function getMode()
	{
		return $this->data['file']['mode'];
	}

	public function getDatabaseValue($key)
	{
		return $this->data['database']['connection'][$key];
	}

	public function getName($type = 'file')
	{
		if ($type === 'file') {
			return $this->data['file']['name'];
		}

		return $this->data['database']['name'];
	}

	public function getBackupId()
	{
		return $this->data['backupId'];
	}

	public function getNameWithExtension($type)
	{
		$name = $this->getName($type);

		return sprintf('%s.zip', $name);
	}

	public function getBatchIterator($type)
	{
		if ($type === 'file') {
			return $this->data['file']['batch']['iterator_position'];
		}

		if ($type === 'database') {
			return $this->data['database']['batch']['iterator_position'];
		}

		return null;
	}

	public function setBatchIterator($type, $value)
	{
		if ($type === 'file') {
			$this->data['file']['batch']['iterator_position'] = $value;
		}

		if ($type === 'database') {
			$this->data['database']['batch']['iterator_position'] = $value;
		}

		return $this;
	}

	public function getBatch($type)
	{
		if ($type === 'file') {
			return $this->data['file']['batch'];
		}

		if ($type === 'database') {
			return $this->data['database']['batch'];
		}
	}

	public function setBatch($type, $value)
	{
		if ($type === 'file') {
			$this->data['file']['batch'] = $value;
		}

		if ($type === 'database') {
			$this->data['database']['batch'] = $value;
		}

		return $this;
	}


	/**
	 * @param string $type (file|database)
	 */
	public function getBatchPart($type)
	{
		if ($type === 'file') {
			return $this->data['file']['batch']['part'];
		}

		if ($type === 'database') {
			return $this->data['database']['batch']['part'];
		}

		return null;
	}

	public function setBatchPart($type, $value)
	{
		if ($type === 'file') {
			$this->data['file']['batch']['part'] = $value;
		}

		if ($type === 'database') {
			$this->data['database']['batch']['part'] = $value;
		}

		return $this;
	}

	public function getExcludeFiles()
	{
		return $this->data['file']['exclude'];
	}

	public function getMaxSize()
	{
		return $this->data['file']['batch']['max_size'];
	}

	public function getIsFileSourceRequired()
	{
		return $this->data['file']['required'];
	}

	public function getIsDatabaseSourceRequired()
	{
		return $this->data['database']['required'];
	}


	public function addFilenameZipSent($filename, $type)
	{
		if ($type === 'file') {
			$this->data['file']['zips_sent'][] = $filename;
		} else {
			$this->data['database']['zips_sent'][] = $filename;
		}

		return $this;
	}

	public function setFinish($type)
	{
		if ($type === 'file') {
			$this->data['file']['finish'] = true;
		}

		if ($type === 'database') {
			$this->data['database']['finish'] = true;
		}

		return $this;
	}

	public function existDatabaseData()
	{
		return isset($this->data['database']['finish']);
	}

	public function getFinish($type)
	{
		if ($type === 'file') {
			return $this->data['file']['finish'];
		}

		if ($type === 'database') {
			return $this->data['database']['finish'];
		}

		return null;
	}

	public function getFileData()
	{
		$secure = isset($options['secure']) ?? false;

		if ( ! $secure) {
			return $this->data['file'];
		}

		$data = $this->data['file'];
		unset($data['base_directory']);

		return $data;
	}

	public function getDatabaseData($options)
	{
		$secure = isset($options['secure']) ?? false;

		if ( ! $secure) {
			return $this->data['database'];
		}

		$data = $this->data['database'];
		if (isset($data['connection'])) {
			unset($data['connection']);
		}

		return $data;
	}

	public function getDatabaseTables()
	{
		return $this->data['database']['tables'];
	}

	public function getTableByCurrentBatch()
	{
		$iterator = $this->getBatchIterator('database');
		$tables   = $this->getDatabaseTables();

		if (isset($tables[$iterator])) {
			return $tables[$iterator];
		}

		return null;
	}

	public function getTableByName($name)
	{
		$tables = $this->getDatabaseTables();

		foreach ($tables as $table) {
			if ($table['name'] === $name) {
				return $table;
			}
		}

		return null;
	}

	public function setTableBatchsByName($name, $value)
	{
		$this->data['database']['table_batchs'][$name] = $value;

		return $this;
	}

	public function getTableBatchsByName($name)
	{
		if (isset($this->data['database']['table_batchs'][$name])) {
			return $this->data['database']['table_batchs'][$name];
		}

		return null;
	}

	public function hasTableNeedBatchByName($name)
	{
		$table = $this->getTableByName($name);

		return $table['need_batch'];
	}
}
