<?php
namespace WPUmbrella\Controller\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Models\AbstractController;

class CurrentProcess extends AbstractController
{
    public function executeGet($params)
    {
        $version = $params['version'] ?? 'v1';

        if (!defined('WP_UMBRELLA_INIT_BACKUP')) {
            define('WP_UMBRELLA_INIT_BACKUP', true);
        }

        $manageProcess = null;
        if ($version === 'v1') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcess');
        } elseif ($version === 'v3') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcessCustomTable');
        }

        if ($manageProcess === null) {
            return $this->returnResponse([
                'is_running' => false,
                'has_action_in_progress' => false,
                'data' => null
            ]);
        }

        $isRunning = $manageProcess->isBackupInProgress();
        $backupDoesHaveActionInProgress = $manageProcess->backupDoesHaveActionInProgress();

        $data = null;
        if ($isRunning) {
            $dataObj = $manageProcess->getBackupData();
            if ($dataObj !== null) {
                $data = $dataObj->getData();
                $data['file'] = $dataObj->getFileData([
                    'secure' => true
                ]);
                $data['database'] = $dataObj->getDatabaseData([
                    'secure' => true
                ]);
            }
        }
        return $this->returnResponse([
            'is_running' => $isRunning,
            'has_action_in_progress' => $backupDoesHaveActionInProgress,
            'data' => $data
        ]);
    }
}
