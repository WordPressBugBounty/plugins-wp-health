<?php
namespace WPUmbrella\Actions\Queue\Backup;

use WPUmbrella\Core\Constants\CodeResponse;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Services\Backup\V2\BackupManageProcess;
use WPUmbrella\Services\Backup\QueueRunner\V2\BackupQueueRunnerFiles;

class BackupQueueV2 implements ExecuteHooks
{
    public function hooks()
    {
        $backupVersion = get_option('wp_umbrella_backup_version');
        if ($backupVersion === 'v4') {
            return;
        }

        add_action(BackupManageProcess::ACTION_BACKUP_FILES, [$this, 'runFiles']);
        add_action(BackupManageProcess::ACTION_BACKUP_DATABASE, [$this, 'runDatabase']);
        add_action(BackupManageProcess::ACTION_BACKUP_PREPARE_BATCH_DATABASE, [$this, 'runPrepareBatchDatabase']);
        add_action(BackupManageProcess::ACTION_BACKUP_CHECK_BATCH_DATABASE, [$this, 'runCheckBatchDatabase']);
        add_action(BackupManageProcess::ACTION_BACKUP_CLEANUP, [$this, 'cleanup']);
        add_action('action_scheduler_failed_action', [$this, 'cleanupBackupIfFailed']);
        add_action('action_scheduler_failed_execution', [$this, 'cleanupBackupIfFailed']);
        add_action('action_scheduler_unexpected_shutdown', [$this, 'cleanupBackupIfFailed']);
    }

    public function cleanupBackupIfFailed($actionId)
    {
        try {
            $action = \ActionScheduler::store()->fetch_action($actionId);
            $hook = $action->get_hook();

            $manageProcess = wp_umbrella_get_service('BackupManageProcess');

            switch ($hook) {
                case BackupManageProcess::ACTION_BACKUP_FILES:
                    $data = $manageProcess->getBackupData();

                    if ($data === null) {
                        return;
                    }

                    if ($data !== null) {
                        $data = $data->getData();
                    } else {
                        $data = [];
                    }
                    $data['type'] = 'file';
                    wp_umbrella_get_service('BackupApi')->postErrorBackup($data);
                    wp_umbrella_get_service('BackupManageProcess')->addSchedulerCleanup();
                    break;
                case BackupManageProcess::ACTION_BACKUP_DATABASE:
                case BackupManageProcess::ACTION_BACKUP_PREPARE_BATCH_DATABASE:
                case BackupManageProcess::ACTION_BACKUP_CHECK_BATCH_DATABASE:
                    $data = $manageProcess->getBackupData();

                    if ($data !== null) {
                        $data = $data->getData();
                    } else {
                        $data = [];
                    }
                    $data['type'] = 'database';
                    wp_umbrella_get_service('BackupApi')->postErrorBackup($data);
                    wp_umbrella_get_service('BackupManageProcess')->addSchedulerCleanup();
                    break;
            }
        } catch (\Exception $e) {
            // Do nothing
        }
    }

    public function runFiles()
    {
        try {
            wp_umbrella_get_service('BackupActionQueueRunnerFile')->handle();
        } catch (\Exception $e) {
        }
    }

    public function runDatabase()
    {
        try {
            wp_umbrella_get_service('BackupActionQueueRunnerDatabase')->handle();
        } catch (\Exception $e) {
        }
    }

    public function runPrepareBatchDatabase()
    {
        wp_umbrella_get_service('BackupActionQueueRunnerPrepareBatchDatabase')->handle();
    }

    /**
     * Check batch database
     */
    public function runCheckBatchDatabase()
    {
        wp_umbrella_get_service('BackupActionQueueRunnerCheckBatchDatabase')->handle();
    }

    public function cleanup()
    {
        wp_umbrella_get_service('BackupManageProcess')->deleteProcess();
        wp_umbrella_get_service('BackupExecutorV2')->cleanup();
    }
}
