<?php
namespace WPUmbrella\Services\Backup\QueueRunner\V2;


use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrella\Services\Backup\V2\BackupDirector;
use WPUmbrella\Core\Constants\CodeResponse;
use WPUmbrella\Core\Constants\BackupData;
use WPUmbrella\Helpers\DataTemporary;

class BackupQueueRunnerDatabase extends AbstractBackupQueue
{
    const TYPE = 'database';

	const NAME_SERVICE =  'BackupQueueRunnerDatabaseV2';


	protected function finishBackupDatabase($data, $version){

		$manageProcess = $this->getManageProcessByVersion($version);

		$data->setFinish(self::TYPE);
		$data->setTimestampEndDate(time());
		$manageProcess->updateBackupData($data->getData());

		// Send to destinations
		$data = $this->sendToDestinations($data, self::TYPE);
		$this->finishBackupAndSaveDataModel($data, self::TYPE);

		if($version === 'v3'){
			wp_umbrella_get_service('BackupRepository')->setFinishByType(
				$data->getUmbrellaBackupId(),
				self::TYPE
			);

			$manageProcess->finishBackup();
		}
	}

	protected function checkBatchIfNeeded($data){
		$currentTable = $data->getTableByCurrentBatch();

		if(!$data->hasTableNeedBatchByName($currentTable['name'])){
			return false;
		}

		$batchs = $data->getTableBatchsByName($currentTable['name']);
		$part = $data->getBatchPart('database');

		if(!isset($batchs[$part])){
			return false;
		}

		if(!$batchs[$part]['need_check']){
			return false;
		}

		return true;

	}

    public function run($options = [])
    {
		$version = $options['version'] ?? 'v1';
		$manageProcess = $this->getManageProcessByVersion($version);

		$data = $manageProcess->getBackupData();

		if($data === null || !$data->existDatabaseData()){ // Exist database needed for prevent fail update_option
			return [
				'success' => true,
				'data' => [
					'code' => CodeResponse::BACKUP_NEED_CLEANUP
				]
			];
		}

		$finish = $data->getFinish(self::TYPE);

		if($finish){
			return [
				'success' => true,
				'data' => [
					'code' => CodeResponse::BACKUP_NEED_CLEANUP
				]
			];
		}


		$part = $data->getBatchPart(self::TYPE);
		$currentTable = $data->getTableByCurrentBatch();

		// Necessary for multiple part
        $name = $manageProcess->createDefaultName([
			'title' => $data->getTitle(),
			'suffix' => $data->getSuffix(),
			'database' => true,
			'part' => $part,
			'backupId' => $data->getBackupId()
		]);

		$data->setName($name, self::TYPE);

		$currentTable = $data->getTableByCurrentBatch();
		$tablesBatchs = $data->getTableBatchsByName($currentTable['name']);

		if($part === 0 && $currentTable['need_batch'] && $tablesBatchs === null){
			wp_umbrella_get_service('TaskBackupLogger')->error("[Table need batch] : " . $currentTable['name']  , $data->getUmbrellaBackupId());
			return [
				'success' => true,
				'data' => [
					'code' => CodeResponse::BACKUP_TABLE_NEED_BATCH
				]
			];
		}

		/**
		 * @var BackupDirector
		 */
        $backupDirector = wp_umbrella_get_service('BackupDirectorV2');
        $builder = new BackupBuilder();


		$checkBatch = $this->checkBatchIfNeeded($data);

		if($checkBatch){
			wp_umbrella_get_service('TaskBackupLogger')->info("[Check batch] : " . $currentTable['name']  , $data->getUmbrellaBackupId());
			return [
				'success' => true,
				'data' => [
					'code' => CodeResponse::BACKUP_TABLE_CHECK_BATCH
				]
			];
		}

        $profile = $backupDirector->constructBackupProfileOnlySQL($builder, $data);

		$backupExecutor = wp_umbrella_get_service('BackupExecutorV2');
        $result = $backupExecutor->backupSources($profile);

		$response = $result[0];

		if (!$response['success']) {
			$dataPost = $data->getData();
			$dataPost['type'] = self::TYPE;
			$dataPost['code_error'] = DataTemporary::getDataByKey('code_error_backup');
			$dataPost['message_error_backup'] = DataTemporary::getDataByKey('message_error_backup');
			$this->api->postErrorBackup($dataPost);
			wp_umbrella_get_service('TaskBackupLogger')->error("[Error during backup database]", $data->getUmbrellaBackupId());
			return [
				'success' => false,
				'data' => [
					'code' => CodeResponse::BACKUP_ERROR
				]
			];
        }

		$newBatchIterator = $response['iterator_position'];
        $newPart = $response['part'];

		$data->setBatchIterator(self::TYPE, $newBatchIterator)
			 ->setBatchPart(self::TYPE, $newPart);

		$currentTableAfterProcess = $data->getTableByCurrentBatch();

		// finish
		if($currentTableAfterProcess === null){
			wp_umbrella_get_service('TaskBackupLogger')->info("[Finish database backup]", $data->getUmbrellaBackupId());
			$manageProcess->updateBackupData($data->getData());

			$this->finishBackupDatabase($data, $version);

			return [
				'success' => true,
				'data' => [
					'code' => CodeResponse::BACKUP_DATABASE_FINISH,
				]
			];
		}
		else{
			$this->saveCurrentDataModel($data, self::TYPE);
		}

		$manageProcess->updateBackupData($data->getData());

		return [
			'success' => true,
			'data' => [
				'current_table' => $currentTableAfterProcess['name'],
				'iterator_position' => $data->getBatchIterator(self::TYPE),
				'part' => $data->getBatchPart(self::TYPE),
				'code' => CodeResponse::BACKUP_NEXT_PART_DATABASE,
			]
		];
    }
}
