<?php
namespace WPUmbrella\Controller\Backup;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\Exceptions\BackupNotCreated;
use WPUmbrella\Core\Constants\CodeResponse;

class Init extends AbstractController
{
    public function fallbackOldProcess($params)
    {
        $runner = wp_umbrella_get_service('BackupRunner');

        if ($runner->hasScheduledBatchInProcess()) {
            return $this->returnResponse(['code' => 'backup_already_process', 'message' => 'A backup is already in process'], 400);
        }

        try {
            wp_umbrella_get_service('BackupInitProcess')->init($params);
        } catch (BackupNotCreated $e) {
            return $this->returnResponse(['code' => 'error', 'message' => $e->getMessage()], 400);
        }

        return $this->returnResponse(['code' => 'success', 'message' => 'Backup scheduled']);
    }

    /**
     * @param array $params
     * 		- string $params['version'] - (v1, v3)
     *
     * v1 : current version - optimized
     * v3 : version without action scheduler
     */
    public function executePost($params)
    {
        $version = $params['version'] ?? 'v1';
        $force = $params['force'] ?? false;

        if ($version === 'v1') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcess');
        } elseif ($version === 'v3') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcessCustomTable');
        }

        if ($manageProcess === null) {
            return $this->returnResponse(['code' => 'error', 'message' => 'Version not found']);
        }

        if ($manageProcess->isBackupInProgress() && !$force) {
            return $this->returnResponse(['code' => CodeResponse::BACKUP_ALREADY_PROCESS, 'message' => 'A backup is already in process'], 400);
        }

        if ((bool) $force) {
            $manageProcess->unscheduledBatch();
        }

        try {
            $data = $manageProcess->init($params);
            return $this->returnResponse(['code' => CodeResponse::SUCCESS, 'data' => $data, 'message' => 'Backup scheduled']);
        } catch (BackupNotCreated $e) {
            return $this->returnResponse(['code' => CodeResponse::ERROR, 'message' => $e->getMessage()], 400);
        }
    }
}
