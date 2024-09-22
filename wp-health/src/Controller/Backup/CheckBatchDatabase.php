<?php
namespace WPUmbrella\Controller\Backup;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\Constants\CodeResponse;

class CheckBatchDatabase extends AbstractController
{
    /**
     * @param array $params
     * 		- string $params['table']
     * 		- string $params['part']
     * 		- string $params['divided_memory']
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

        $part = $params['part'] ?? null;

        if ($part === null) {
            return $this->returnResponse([
                'code' => CodeResponse::ERROR,
                'message' => 'Part not found'
            ]);
        }

        $version = $params['version'] ?? 'v1';

        if ($version === 'v1') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcess');
        } elseif ($version === 'v3') {
            $manageProcess = wp_umbrella_get_service('BackupManageProcessCustomTable');
        }

        if (!$manageProcess) {
            return $this->returnResponse([
                'code' => CodeResponse::ERROR,
                'message' => 'Version not found'
            ]);
        }

        if (!$manageProcess->isBackupInProgress()) {
            return $this->returnResponse(['code' => CodeResponse::ERROR, 'message' => 'No backup'], 400);
        }

        if (!defined('WP_UMBRELLA_INIT_BACKUP')) {
            define('WP_UMBRELLA_INIT_BACKUP', true);
        }

        $dividedMemory = null;
        try {
            $dividedMemory = isset($params['divided_memory']) ? floatval($params['divided_memory']) : null;
        } catch (\Exception $e) {
            // do nothing
        }

        try {
            $data = $manageProcess->getBackupData();

            $currentTable = $data->getTableByName($table);
            $maximumMemoryAuthorized = $data->getMaximumMemoryAuthorized();

            if ($dividedMemory !== null && $dividedMemory > 0) {
                $maximumMemoryAuthorized = $maximumMemoryAuthorized / $dividedMemory;
            }

            $batchs = $data->getTableBatchsByName($currentTable['name']);

            if (!isset($batchs[$part])) {
                return $this->returnResponse([
                    'code' => CodeResponse::ERROR,
                    'message' => 'Part not found'
                ]);
            }

            if (!$batchs[$part]['need_check'] && $dividedMemory === null) {
                return $this->returnResponse([
                    'code' => CodeResponse::ERROR,
                    'params' => $params,
                    'message' => 'Part already done'
                ]);
            }

            $newBatchs = wp_umbrella_get_service('BackupDatabaseConfigurationV2')->preventInitBatchs(
                $currentTable,
                [
                    'maximum_memory' => $maximumMemoryAuthorized,
                ],
                [$batchs[$part]]
            );

            unset($batchs[$part]);
            array_splice($batchs, $part, 0, $newBatchs);

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
