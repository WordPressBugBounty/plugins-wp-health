<?php
namespace WPUmbrella\Services\Backup\QueueRunner\V2;


use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrellaBackup\Api\Backup;
use WPUmbrella\Services\Backup\QueueRunner\AbstractBackupQueueRunner;

class AbstractBackupQueue extends AbstractBackupQueueRunner
{

	public function setApi($api){
		$this->api = $api;
		return $this;
	}

    protected function sendToDestinations($data, $type)
	{
        $backupDirector = wp_umbrella_get_service('BackupDirectorV2');
        $builder = new BackupBuilder();


        $profile = $backupDirector->constructBackupProfileDestination($builder, $data, [
			'type' => $type,
			'api' => $this->api
		]);

        $backupExecutor = wp_umbrella_get_service('BackupExecutorV2');
        $backupExecutor->sendToDestinations($profile, $data);
        $backupExecutor->cleanup();

        $data->addFilenameZipSent($data->getNameWithExtension($type), $type);
        return $data;
    }

    protected function saveCurrentDataModel($data, $type)
    {
		$data = $data->getData();
		$data['type'] = $type;

        $this->api->putUpdateBackupData($data);
    }

    protected function finishBackupAndSaveDataModel($data, $type)
    {
		$data = $data->getData();
		$data['type'] = $type;

        $this->api->postFinishBackup($data);
    }
}
