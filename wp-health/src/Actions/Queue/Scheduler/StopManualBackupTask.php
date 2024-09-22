<?php

namespace WPUmbrella\Actions\Queue\Scheduler;

use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Core\Scheduler\AsyncQueueRunner;
use WPUmbrella\Core\Scheduler\QueueRunner;
use WPUmbrella\Services\Repository\TaskBackupRepository;
use WPUmbrella\Services\Scheduler\SchedulerLock;
use WPUmbrella\Services\Scheduler\ScheduleTaskBackup;

use function add_action;
use function intval;
use function sanitize_text_field;
use function wp_die;
use function wp_verify_nonce;


class StopManualBackupTask implements ExecuteHooks
{
	const ACTION = 'wp_umbrella_stop_manual_backup_task';
	const NONCE = 'wp_umbrella_stop_manual_backup_task_nonce';

	/**
	 * @var ScheduleTaskBackup
	 */
	protected $scheduler;

	protected $schedulerLock;

	/**
	 * @var BackupRepository
	 */
	protected $backupRepository;

	protected $taskBackupRepository;

	public function __construct()
	{
		$this->scheduler     = wp_umbrella_get_service('ScheduleTaskBackup');
		$this->schedulerLock = wp_umbrella_get_service('SchedulerLock');
		$this->backupRepository = wp_umbrella_get_service('BackupRepository');
		$this->taskBackupRepository = wp_umbrella_get_service('TaskBackupRepository');
	}

	public function hooks()
	{
		add_action('admin_post_' . self::ACTION, [$this, 'handle']);
	}

	public function handle()
	{
		if( ! wp_verify_nonce( $_REQUEST[self::NONCE], self::ACTION ) ) {
			wp_die('Invalid nonce');
		}

		$backupId = intval(sanitize_text_field($_REQUEST['backup_id']));

		$backup = $this->backupRepository->getBackupById($backupId);
		if( is_null($backup)){
			wp_die('Invalid backup id');
		}


		$this->backupRepository->stopBackup($backupId);
		$this->taskBackupRepository->setStoppedTasksByBackupId($backupId);
		wp_umbrella_get_service('BackupExecutorV2')->cleanup();

		wp_redirect(wp_get_referer());
		exit;
	}
}