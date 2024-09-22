<?php
namespace WPUmbrella\Services\Backup\QueueRunner\V2;

use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrella\Services\Backup\V2\BackupDirector;

class BackupQueuePrepareDatabaseBatch extends AbstractBackupQueue
{

    public function run($options = [])
    {

		$version = $options['version'] ?? 'v1';
		$manageProcess = $this->getManageProcessByVersion($version);

		$data = $manageProcess->getBackupData();

		try {
			$table = $data->getTableByCurrentBatch()['name'];

			$batchs = wp_umbrella_get_service('BackupDatabaseConfigurationV2')->getBatchForTable($data->getTableByName($table));

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
