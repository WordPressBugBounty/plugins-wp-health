<?php
namespace WPUmbrella\Services\Backup\QueueRunner;


use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrellaBackup\Api\Backup;

class AbstractBackupQueueRunner
{

	protected function getManageProcessByVersion($version){
		if($version === 'v1'){
			return wp_umbrella_get_service('BackupManageProcess');
		}

		return wp_umbrella_get_service('BackupManageProcessCustomTable');
	}

}
