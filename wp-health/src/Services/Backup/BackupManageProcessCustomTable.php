<?php
namespace WPUmbrella\Services\Backup;

use WPUmbrella\Models\Backup\BackupProcessedData;
use WPUmbrella\Core\Exceptions\BackupNotCreated;
use WPUmbrella\Services\Backup\BackupBatchData;
use WPUmbrella\Models\Backup\V2\BackupConfigData;
use WPUmbrella\Core\Constants\BackupData;
use WPUmbrella\Core\Constants\BackupTaskType;
use WPUmbrella\Services\Backup\AbstractbackupManageProcess;
use WPUmbrella\Services\Repository\BackupRepository;
use WPUmbrella\Helpers\DataTemporary;

class BackupManageProcessCustomTable extends AbstractbackupManageProcess
{
    /**
     * @var BackupRepository
     */
    protected $backupRepository;

    protected $taskBackupRepository;

    public function __construct()
    {
        $this->backupRepository = wp_umbrella_get_service('BackupRepository');
        $this->taskBackupRepository = wp_umbrella_get_service('TaskBackupRepository');
    }

    public function getPathFileConfig()
    {
        return sprintf('%s/config-v3.php', WP_UMBRELLA_DIR_WPU_BACKUP);
    }

    public function finishBackup()
    {
        $this->backupRepository->finishBackup($this->getBackupData()->getUmbrellaBackupId());
    }

    public function backupDoesHaveActionInProgress() : bool
    {
        try {
            return $this->taskBackupRepository->hasAtLeastOneTaskInProgress();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isBackupInProgress() : bool
    {
        $data = $this->backupRepository->hasBackupInProgress();

        if (!$data) {
            return false;
        }

        return true;
    }

    public function deleteProcess()
    {
        if (!file_exists($this->getPathFileConfig())) {
            return;
        }

        wp_umbrella_remove_file($this->getPathFileConfig());
    }

    public function init($params)
    {
        $data = parent::init($params);

        $this->insertBackupData($data);

        return $data;
    }

    public function getBackupData()
    {
        $data = DataTemporary::getDataByKey('backup_data');
        if ($data) {
            return $data;
        }

        $backup = $this->backupRepository->getBackupInProgress();

        if (is_null($backup)) {
            return null;
        }

        if (is_null($backup->getBackupId())) {
            return null;
        }

        $data = new BackupConfigData([]);
        $data->setFromBackup($backup);
        DataTemporary::setDataByKey('backup_data', $data);

        return $data;
    }

    protected function getUpdateBackupDataMethod($type)
    {
        $method = null;

        if ($type === 'database') {
            $method = 'database';
        } elseif ($type === 'file') {
            // Can't write file and can write database
            if (!$this->isWritableData() && function_exists('update_option')) { // Check if table exist
                $method = 'database';
            } else {
                $method = 'file';
            }
        }

        return $method;
    }

    public function insertBackupData($data)
    {
        if (!isset($data['backupId'])) {
            return;
        }

        $this->data = $data;

        $updateDataMethod = isset($data['update_data_method']) ? $data['update_data_method'] : 'database';
        $method = $this->getUpdateBackupDataMethod($updateDataMethod);

        if ($method === 'file') {
            $content = sprintf("<?php

			if(!defined('WP_UMBRELLA_INIT_BACKUP')){
				die('Oups');
			}

			return %s;", var_export($data, true));

            $fp = fopen($this->getPathFileConfig(), 'w');
            if ($fp === false) {
                $this->addSchedulerCleanup();
            } else {
                fwrite($fp, $content);
                fclose($fp);
            }

            return;
        }

        $backupId = isset($data['backupId']) ? $data['backupId'] : null;

        // Database
        $id = $this->backupRepository->insertBackup([
            'count_attachments' => isset($data['snapshot']['count_attachments']) ? $data['snapshot']['count_attachments'] : 0,
            'count_public_posts' => isset($data['snapshot']['count_public_posts']) ? $data['snapshot']['count_public_posts'] : 0,
            'count_plugins' => isset($data['snapshot']['plugins']) ? count($data['snapshot']['plugins']) : 0,
            'wp_core_version' => isset($data['wordpress']['wordpress_version']) ? $data['wordpress']['wordpress_version'] : '',
            'config_database' => isset($data['database']) ? json_encode($data['database']) : null,
            'config_file' => isset($data['file']) ? json_encode($data['file']) : null,
            'title' => isset($data['title']) ? $data['title'] : '',
            'suffix' => isset($data['suffix']) ? $data['suffix'] : '',
            'is_scheduled' => isset($data['is_scheduled']) ? $data['is_scheduled'] : false,
            'backupId' => $backupId,
            'incremental_date' => isset($data['incremental_date']) ? $data['incremental_date'] : null,
        ]);

        if ($data['file']['required']) {
            $taskType = BackupTaskType::BACKUP_FILES;
        } elseif ($data['database']['required']) {
            $taskType = BackupTaskType::BACKUP_DATABASE;
        }

        $this->taskBackupRepository->insertTask([
            'type' => $taskType,
            'backupId' => $id
        ]);
    }

    public function updateBackupData($data)
    {
        if (!isset($data['umbrella_backup_id'])) {
            return;
        }

        $updateDataMethod = isset($data['update_data_method']) ? $data['update_data_method'] : 'database';
        $method = $this->getUpdateBackupDataMethod($updateDataMethod);

        if ($method === 'file') {
            $content = sprintf("<?php

			if(!defined('WP_UMBRELLA_INIT_BACKUP')){
				die('Oups');
			}

			return %s;", var_export($data, true));

            $fp = fopen($this->getPathFileConfig(), 'w');
            if ($fp === false) {
                $this->addSchedulerCleanup();
            } else {
                fwrite($fp, $content);
                fclose($fp);
            }

            return;
        }

        $args = [];
        if (isset($data['database'])) {
            $args['config_database'] = json_encode($data['database']);
        }

        if (isset($data['file'])) {
            $args['config_file'] = json_encode($data['file']);
        }

        if (!empty($args)) {
            $this->backupRepository->updateBackup($data['umbrella_backup_id'], $args);
        }
    }

    public function addSchedulerBatchFiles()
    {
        $data = $this->getBackupData();

        $nextTaskAlreadyExist = $this->taskBackupRepository->getNextTaskByBackupId($data->getUmbrellaBackupId());

        if (!is_null($nextTaskAlreadyExist)) {
            return;
        }

        $this->taskBackupRepository->insertTask([
            'type' => BackupTaskType::BACKUP_FILES,
            'backupId' => $data->getUmbrellaBackupId()
        ]);
    }

    public function addSchedulerBatchDatabase()
    {
        $data = $this->getBackupData();

        $nextTaskAlreadyExist = $this->taskBackupRepository->getNextTaskByBackupId($data->getUmbrellaBackupId());

        if (!is_null($nextTaskAlreadyExist)) {
            return;
        }

        $this->taskBackupRepository->insertTask([
            'type' => BackupTaskType::BACKUP_DATABASE,
            'backupId' => $data->getUmbrellaBackupId()
        ]);
    }

    public function addSchedulerPrepareBatchDatabase()
    {
        $data = $this->getBackupData();

        $nextTaskAlreadyExist = $this->taskBackupRepository->getNextTaskByBackupId($data->getUmbrellaBackupId());

        if (!is_null($nextTaskAlreadyExist)) {
            return;
        }

        $this->taskBackupRepository->insertTask([
            'type' => BackupTaskType::BACKUP_PREPARE_BATCH_DATABASE,
            'backupId' => $data->getUmbrellaBackupId()
        ]);
    }

    public function addSchedulerCleanup()
    {
        $data = $this->getBackupData();

        $nextTaskAlreadyExist = $this->taskBackupRepository->getNextTaskByBackupId($data->getUmbrellaBackupId());
        if (!is_null($nextTaskAlreadyExist)) {
            return;
        }

        $this->taskBackupRepository->insertTask([
            'type' => BackupTaskType::BACKUP_NEED_CLEANUP,
            'backupId' => $data->getUmbrellaBackupId()
        ]);
    }

    public function addSchedulerCheckBatchDatabase()
    {
        $data = $this->getBackupData();

        $nextTaskAlreadyExist = $this->taskBackupRepository->getNextTaskByBackupId($data->getUmbrellaBackupId());
        if (!is_null($nextTaskAlreadyExist)) {
            return;
        }

        $this->taskBackupRepository->insertTask([
            'type' => BackupTaskType::BACKUP_TABLE_CHECK_BATCH,
            'backupId' => $data->getUmbrellaBackupId()
        ]);
    }

    public function unscheduledBatch()
    {
        parent::unscheduledBatch();

        $data = $this->getBackupData();
        $backupId = $data->getUmbrellaBackupId();

        $this->backupRepository->stopBackup($backupId);
        $this->taskBackupRepository->setStoppedTasksByBackupId($backupId);
    }
}
