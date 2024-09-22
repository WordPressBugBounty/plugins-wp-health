<?php
namespace WPUmbrella\Services\Scheduler;

use WPUmbrella\Core\UmbrellaDateTime;
use WPUmbrella\Core\Scheduler\MemoryLimit;
use WPUmbrella\Core\Scheduler\TimeLimit;
use WPUmbrella\Models\Backup\BackupTask;
use WPUmbrella\Services\Api\Backup;
use WPUmbrella\Services\Repository\TaskBackupRepository;

class ScheduleErrorCheck implements Scheduler
{
    use TimeLimit;
    use MemoryLimit;

    /**
     * @var TaskBackupRepository
     */
    protected $taskBackupRepository;

    /**
     * @var Backup
     */
    protected $backupApi;

    /**
     * @var BackupRepository
     */
    protected $backupRepository;

    public function __construct()
    {
        $this->taskBackupRepository = wp_umbrella_get_service('TaskBackupRepository');
        $this->backupRepository = wp_umbrella_get_service('BackupRepository');
        $this->backupApi = wp_umbrella_get_service('Backup');
    }

    public function isAllowed(): bool
    {
        return !$this->memoryExceeded();
    }

    public function execute()
    {
        $tasksInProgress = $this->taskBackupRepository->getTasksInProgress();

        if (count($tasksInProgress) < 1) {
            return;
        }

        $tasksInError = array_filter(
            $tasksInProgress,
            function (BackupTask $task) {
                return $this->taskIsInError($task);
            }
        );

        if (!$tasksInError || !is_array($tasksInError)) {
            return;
        }

        $backupData = wp_umbrella_get_service('BackupManageProcessCustomTable')->getBackupData();

        if (is_null($backupData)) {
            return;
        }

        $this->taskBackupRepository->setErrorTask($tasksInError[0]->getId());
        $this->backupRepository->setBackupInError($backupData->getUmbrellaBackupId());

        wp_umbrella_get_service('BackupApi')->postErrorBackup(
            $backupData->getData()
        );
    }

    public function taskIsInError(BackupTask $task): bool
    {
        $now = new UmbrellaDateTime();

        if ($now->diff($task->getDateStart())->i >= 10) {
            return true;
        }

        return false;
    }
}
