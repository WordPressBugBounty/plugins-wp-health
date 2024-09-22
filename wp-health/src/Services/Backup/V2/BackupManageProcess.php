<?php
namespace WPUmbrella\Services\Backup\V2;


use WPUmbrella\Models\Backup\BackupProcessedData;
use WPUmbrella\Core\Exceptions\BackupNotCreated;
use WPUmbrella\Services\Backup\BackupBatchData;
use WPUmbrella\Models\Backup\V2\BackupConfigData;
use WPUmbrella\Core\Constants\BackupData;
use WPUmbrella\Services\Backup\AbstractbackupManageProcess;

use ActionScheduler;
use ActionScheduler_Store;


class BackupManageProcess extends AbstractbackupManageProcess
{
    const GROUP = 'wp-umbrella-v2';

    const ACTION_BACKUP_FILES = 'wp_umbrella_backup_files_v2_batch';

    const ACTION_BACKUP_DATABASE = 'wp_umbrella_backup_database_v2_batch';

	const ACTION_BACKUP_PREPARE_BATCH_DATABASE = 'wp_umbrella_backup_prepare_batch_database';

	const ACTION_BACKUP_CHECK_BATCH_DATABASE = 'wp_umbrella_backup_check_batch_database';

	const ACTION_BACKUP_CLEANUP = 'wp_umbrella_backup_cleanup';

	protected $data;


	public function backupDoesHaveActionInProgress() : bool {
		try {
			$args = [
				'group' => self::GROUP,
				'per_page' => 1,
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'claimed' => false
			];

			$actionsIds = as_get_scheduled_actions($args, 'ids');

			if (empty($actionsIds)) {
				$args = [
					'group' => self::GROUP,
					'per_page' => 1,
					'status' => ActionScheduler_Store::STATUS_RUNNING,
					'claimed' => false
				];

				$actionsIdsRunning = as_get_scheduled_actions($args, 'ids');

				if(!empty($actionsIdsRunning)){
					return true;
				}

				return false;
			}

			return true;
		}
		catch (\Exception $e) {
			return false;
		}
	}

    public function isBackupInProgress() : bool {

		$data = get_option('wp_umbrella_backup_data_process');

		if(!$data){
			if($this->isWritableData()){
				return file_exists( $this->getPathFileConfig() );
			}

			return false;
		}

		return true;


	}

    public function deleteProcess(){
		if(function_exists('delete_option')){
			delete_option('wp_umbrella_backup_suffix_security');
			delete_option('wp_umbrella_backup_data_process');
		}

        if(! file_exists( $this->getPathFileConfig() ) ){
            return;
        }

        wp_umbrella_remove_file( $this->getPathFileConfig() );
    }


    public function init($params)
    {
		$data = parent::init($params);

		// Delete data not required for backup
		if(isset($data['snapshot'])){
			unset($data['snapshot']);
		}
		if(isset($data['wordpress'])){
			unset($data['wordpress']);
		}
		if(isset($data['checksum'])){
			unset($data['checksum']);
		}

		// Write file config with data with var_export
		$this->updateBackupData($data);

		// With Action Scheduler
		if($data['file']['required']){
			$this->addSchedulerBatchFiles();
		}
		else if($data['database']['required']){
			$this->addSchedulerBatchDatabase();
		}

        return $data;
    }

    public function getBackupData(){
		if($this->data !== null){
			return $this->data;
		}

		$data = get_option('wp_umbrella_backup_data_process');
		if(!$data && file_exists($this->getPathFileConfig())) {
			$data = include $this->getPathFileConfig();
		}
		$data = maybe_unserialize($data);

		if(!$data){
			return null;
		}

		if(!isset($data['backupId'])){
			return null;
		}

		$this->data = new BackupConfigData($data);

		return $this->data;
    }

	protected function getUpdateBackupDataMethod($type){

		$method = null;

		if($type === 'database'){
			if(function_exists('update_option')){
				$method = 'database';
			}
			else{
				$method = 'file';
			}
		}
		else if($type === 'file'){

			// Can't write file and can write database
			if(!$this->isWritableData() && function_exists('update_option')){
				$method = 'database';
			}
			else{
				$method = 'file';
			}
		}

		return $method;
	}

	public function updateBackupData($data){
		$this->data = $data;

		$updateDataMethod = isset($data['update_data_method']) ? $data['update_data_method'] : 'database';
		$method = $this->getUpdateBackupDataMethod($updateDataMethod);

		if($method === 'database'){
			update_option('wp_umbrella_backup_data_process', $data, false);
		}
		else if($method === 'file'){
			$content = sprintf("<?php

			if(!defined('WP_UMBRELLA_INIT_BACKUP')){
				die('Oups');
			}

			return %s;", var_export($data, true));

			$fp = fopen($this->getPathFileConfig(), 'w');
			if($fp === false){
				$this->addSchedulerCleanup();
			}
			else{
				fwrite($fp, $content);
				fclose($fp);
			}
		}
	}


    public function addSchedulerBatchFiles()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_FILES, [], self::GROUP);
    }

    public function addSchedulerBatchDatabase()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_DATABASE, [], self::GROUP);
    }

    public function addSchedulerPrepareBatchDatabase()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_PREPARE_BATCH_DATABASE, [], self::GROUP);
    }

	public function addSchedulerCleanup()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_CLEANUP, [], self::GROUP);
    }

	public function addSchedulerCheckBatchDatabase()
    {
        as_schedule_single_action(time() + 1, self::ACTION_BACKUP_CHECK_BATCH_DATABASE, [], self::GROUP);
    }

    public function unscheduledBatch()
    {
        as_unschedule_action(self::ACTION_BACKUP_FILES, [], self::GROUP);
        as_unschedule_action(self::ACTION_BACKUP_DATABASE, [], self::GROUP);
        as_unschedule_action(self::ACTION_BACKUP_PREPARE_BATCH_DATABASE, [], self::GROUP);

		parent::unscheduledBatch();

    }

}
