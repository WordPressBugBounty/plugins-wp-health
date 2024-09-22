<?php
namespace WPUmbrella\Controller\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Backup\V2\BackupManageProcess;
use ActionScheduler;
use ActionScheduler_QueueRunner;
use ActionScheduler_Store;
use ActionScheduler_Action;

class RunProcess extends AbstractController
{

	protected function runV1(){
		$manageProcess = wp_umbrella_get_service('BackupManageProcess');

		$isRunning = $manageProcess->isBackupInProgress();

		if(!$isRunning){
			return $this->returnResponse([
				'code' => 'no_process',
			]);
		}


		if(!function_exists('as_get_scheduled_actions')){
			return $this->returnResponse([
				'code' => 'no_action_scheduler',
			]);
		}

		$args = [
            'group' => BackupManageProcess::GROUP,
            'per_page' => 1,
            'status' => ActionScheduler_Store::STATUS_PENDING,
            'claimed' => false
        ];


        $actionsIds = as_get_scheduled_actions($args, 'ids');

        if (empty($actionsIds)) {
			return $this->returnResponse([
				'code' => 'in_progress',
			]);
        }

		try {
			$actionId = current($actionsIds);

			$runner = new ActionScheduler_QueueRunner();
			$runner->process_action( $actionId, 'WP Umbrella Backup' );
		} catch (\Exception $e) {
			return $this->returnResponse([
				'code' => 'error',
				'message' => $e->getMessage(),
			]);
		}

        return $this->returnResponse([
            'code' => 'success'
        ]);

	}

	protected function runV3(){
		$manageProcess = wp_umbrella_get_service('BackupManageProcessCustomTable');

		$isRunning = $manageProcess->isBackupInProgress();
		if(!$isRunning){
			return $this->returnResponse([
				'code' => 'no_process',
			]);
		}


		$task = wp_umbrella_get_service('TaskBackupRepository')->getNextTask();

        if (is_null($task)) {
			return $this->returnResponse([
				'code' => 'in_progress',
			]);
        }

		try {
			wp_umbrella_get_service('ScheduleTaskBackup')->runTask($task);
		} catch (\Exception $e) {
			return $this->returnResponse([
				'code' => 'error',
				'message' => $e->getMessage(),
			]);
		}

        return $this->returnResponse([
            'code' => 'success'
        ]);
	}

    public function executePost($params)
    {
		if(!defined('WP_UMBRELLA_INIT_BACKUP')){
			define('WP_UMBRELLA_INIT_BACKUP', true);
		}

		$version = $params['version'] ?? 'v1';

		if($version === 'v1'){
			return $this->runV1();
		}
		else if($version === 'v3'){
			return $this->runV3();
		}
    }
}
