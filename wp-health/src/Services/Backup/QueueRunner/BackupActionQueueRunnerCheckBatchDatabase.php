<?php
namespace WPUmbrella\Services\Backup\QueueRunner;

use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrellaBackup\Api\Backup;
use WPUmbrella\Core\Constants\CodeResponse;

class BackupActionQueueRunnerCheckBatchDatabase extends AbstractBackupQueueRunner
{
    public function handle($options = [])
    {
        if (!defined('WP_UMBRELLA_INIT_BACKUP')) {
            define('WP_UMBRELLA_INIT_BACKUP', true);
        }

        $version = $options['version'] ?? 'v1';

        $manageProcess = $this->getManageProcessByVersion($version);

        try {
            $runner = wp_umbrella_get_service('BackupQueueCheckDatabaseBatch');
            $response = $runner->run([
                'divided_memory' => 2,
                'version' => $version
            ]);

            $success = $response['success'];
        } catch (\Exception $e) {
            $success = false;
        }

        if ($success) {
            $manageProcess->addSchedulerBatchDatabase();
        } else {
            // Error
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

        return $success ? CodeResponse::SUCCESS : CodeResponse::BACKUP_ERROR;
    }
}
