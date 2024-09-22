<?php
namespace WPUmbrella\Services\Backup\QueueRunner;

use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrellaBackup\Api\Backup;
use WPUmbrella\Core\Constants\CodeResponse;

class BackupActionQueueRunnerDatabase extends AbstractBackupQueueRunner
{
    public function handle($options = [])
    {
        if (!defined('WP_UMBRELLA_INIT_BACKUP')) {
            define('WP_UMBRELLA_INIT_BACKUP', true);
        }

        $version = $options['version'] ?? 'v1';

        $manageProcess = $this->getManageProcessByVersion($version);

        try {
            $runner = wp_umbrella_get_service('BackupQueueRunnerDatabaseV2');
            $runner->setApi(wp_umbrella_get_service('BackupApi'));
            $response = $runner->run([
                'version' => $version
            ]);
            $code = $response['data']['code'];
        } catch (\Exception $e) {
            wp_umbrella_get_service('TaskBackupLogger')->info("Code response: {$code}", $manageProcess->getBackupData()->getUmbrellaBackupId());
            $code = CodeResponse::BACKUP_ERROR;
        }

        switch($code) {
            case CodeResponse::BACKUP_TABLE_CHECK_BATCH:
                $manageProcess->addSchedulerCheckBatchDatabase();
                break;
            case CodeResponse::BACKUP_NEXT_PART_DATABASE:
                $manageProcess->addSchedulerBatchDatabase();
                break;
            case CodeResponse::BACKUP_TABLE_NEED_BATCH:
                $manageProcess->addSchedulerPrepareBatchDatabase();
                break;
            case CodeResponse::BACKUP_DATABASE_FINISH:
                $manageProcess->addSchedulerCleanup();
                break;
            case CodeResponse::BACKUP_ERROR:
                $data = $manageProcess->getBackupData();
                if ($data !== null) {
                    $data = $data->getData();
                } else {
                    $data = [];
                }
                $data['type'] = 'database';
                wp_umbrella_get_service('BackupApi')->postErrorBackup($data);
                $manageProcess->addSchedulerCleanup();
        }

        return $code !== CodeResponse::BACKUP_ERROR ? CodeResponse::SUCCESS : CodeResponse::BACKUP_ERROR;
    }
}
