<?php
namespace WPUmbrella\Controller\Backup;

use WPUmbrella\Core\Models\AbstractController;

class DeleteProcess extends AbstractController
{
    public function executeDelete($params)
    {
        $version = isset($_GET['version']) ? $_GET['version'] : 'v1';

        if ($version === 'v1') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcess');
        } elseif ($version === 'v3') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcessCustomTable');
        }

        if ($manageProcess === null) {
            return $this->returnResponse(['code' => 'error', 'message' => 'Version not found']);
        }

        $manageProcess->unscheduledBatch();

        return $this->returnResponse(['code' => 'success', 'message' => 'Backup unscheduled']);
    }
}
