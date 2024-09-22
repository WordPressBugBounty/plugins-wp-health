<?php
namespace WPUmbrella\Controller\Backup;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\Constants\CodeResponse;

class PrepareBatchDatabase extends AbstractController
{
    /**
     * @param array $params
     * 		- string $params['table']
     */
    public function executePost($params)
    {
        $table = $params['table'] ?? null;

        if (!$table) {
            return $this->returnResponse([
                'code' => CodeResponse::ERROR,
                'message' => 'Table not found'
            ]);
        }

        $version = $params['version'] ?? 'v1';

        if ($version === 'v1') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcess');
        } elseif ($version === 'v3') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcessCustomTable');
        }

        if ($manageProcess === null) {
            return $this->returnResponse(['code' => 'error', 'message' => 'Version not found']);
        }

        if (!$manageProcess->isBackupInProgress()) {
            return $this->returnResponse(['code' => CodeResponse::ERROR, 'message' => 'No backup'], 400);
        }

        if (!defined('WP_UMBRELLA_INIT_BACKUP')) {
            define('WP_UMBRELLA_INIT_BACKUP', true);
        }

        try {
            $data = $manageProcess->getBackupData();

            $batchs = wp_umbrella_get_service('BackupDatabaseConfigurationV2')->getBatchForTable($data->getTableByName($table));

            $data->setTableBatchsByName($table, $batchs);
            $manageProcess->updateBackupData($data->getData());

            return $this->returnResponse([
                'code' => CodeResponse::SUCCESS,
                'data' => $batchs
            ]);
        } catch (\Exception $e) {
            return $this->returnResponse(['code' => CodeResponse::ERROR, 'message' => $e->getMessage()], 400);
        }
    }
}
