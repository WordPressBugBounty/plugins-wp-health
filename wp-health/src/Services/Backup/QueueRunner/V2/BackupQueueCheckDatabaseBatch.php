<?php
namespace WPUmbrella\Services\Backup\QueueRunner\V2;

use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrella\Services\Backup\V2\BackupDirector;
use WPUmbrella\Core\Constants\CodeResponse;

class BackupQueueCheckDatabaseBatch extends AbstractBackupQueue
{

    public function run($options = [])
    {

		$version = $options['version'] ?? 'v1';

		$manageProcess = $this->getManageProcessByVersion($version);

		$data = $manageProcess->getBackupData();

		$table = $data->getTableByCurrentBatch()['name'];
		$part  = $data->getBatchPart('database');

		$dividedMemory = null;
		try {
			$dividedMemory = isset($options['divided_memory']) ? floatval($options['divided_memory']) : null;
		} catch (\Exception $e) {
			// do nothing
		}

        try {
			$currentTable = $data->getTableByName($table);
			$maximumMemoryAuthorized = $data->getMaximumMemoryAuthorized();

			if($dividedMemory !== null && $dividedMemory > 0){
				$maximumMemoryAuthorized = $maximumMemoryAuthorized / $dividedMemory;
			}

			$batchs = $data->getTableBatchsByName($currentTable['name']);

			if(!isset($batchs[$part])){
				return [
					'success' => false,
				];
			}

			if(!$batchs[$part]['need_check'] && $dividedMemory === null){
				return [
					'success' => false,
				];
			}

			$newBatchs = wp_umbrella_get_service('BackupDatabaseConfigurationV2')->preventInitBatchs(
				$currentTable,
				[
					'maximum_memory' => $maximumMemoryAuthorized,
				],
				[$batchs[$part]]
			);


			unset($batchs[$part]);
			array_splice($batchs, $part, 0, $newBatchs);

			$data->setTableBatchsByName($table, $batchs);
			$manageProcess->updateBackupData($data->getData());

			return [
				'success' => true,
			];

        } catch (\Exception $e) {
            return [
				'success' => false,
			];
        }

    }
}
