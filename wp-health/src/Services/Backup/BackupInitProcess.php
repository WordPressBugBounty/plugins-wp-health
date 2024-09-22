<?php
namespace WPUmbrella\Services\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupProcessedData;
use WPUmbrella\Core\Exceptions\BackupNotCreated;
use WPUmbrella\Services\Backup\BackupBatchData;

class BackupInitProcess
{
    public function init($params)
    {
        $params['file_source']['required'] = isset($params['files']) ? $params['files'] : false;
        $params['sql_source']['required'] = isset($params['sql']) ? $params['sql'] : false;

        $params['file_source']['exclude_files'] = isset($params['exclude_files']) ? $params['exclude_files'] : [];
        $params['sql_source']['exclude_tables'] = isset($params['exclude_tables']) ? $params['exclude_tables'] : [];

		$params['file_source']['base_directory'] = apply_filters('wp_umbrella_file_source_base_directory', isset($params['base_directory']) ? $params['base_directory'] : ABSPATH);

        $dataProcessed = new BackupProcessedData();
        $dataProcessed->initData($params);

        $backupData = wp_umbrella_get_service('BackupApi')->postInitBackup($dataProcessed->getData());

        if ($backupData === null || !isset($backupData['id'])) {
            throw new BackupNotCreated('Error Processing Create Backup');
        }

        wp_umbrella_get_service('BackupExecutor')->cleanupScratchBackup();

        $dataProcessed->setBackupId($backupData['id']);

        /**
         * @var BackupBatchData
         */
        $batchData = wp_umbrella_get_service('BackupBatchData');

        $dataProcessed->createDefaultName($dataProcessed->getSuffix(), true);
        $batchData->setDataOption($dataProcessed->getData(), 'database');

        $dataProcessed->createDefaultName($dataProcessed->getSuffix());
        $batchData->setDataOption($dataProcessed->getData(), 'file');

        if ($dataProcessed->getIsFileSourceRequired()) {
            wp_umbrella_get_service('BackupRunner')->scheduledBatchFiles();
        }

        if ($dataProcessed->getIsSqlSourceRequired()) {
            wp_umbrella_get_service('BackupRunner')->scheduledBatchDatabase();
        }
    }
}
