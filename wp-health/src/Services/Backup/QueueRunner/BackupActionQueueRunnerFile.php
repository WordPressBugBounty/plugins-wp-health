<?php
namespace WPUmbrella\Services\Backup\QueueRunner;

use WPUmbrella\Core\Constants\CodeResponse;

class BackupActionQueueRunnerFile extends AbstractBackupQueueRunner
{
    public function handle($options = [])
    {
        if (!defined('WP_UMBRELLA_INIT_BACKUP')) {
            define('WP_UMBRELLA_INIT_BACKUP', true);
        }

        $version = $options['version'] ?? 'v1';

        $manageProcess = $this->getManageProcessByVersion($version);

        try {
            $runner = wp_umbrella_get_service('BackupQueueRunnerFilesV2');
            $runner->setApi(wp_umbrella_get_service('BackupApi'));
            $response = $runner->run([
                'version' => $version
            ]);
            $code = $response['data']['code'];
        } catch (\Exception $e) {
            wp_umbrella_get_service('TaskBackupLogger')->error($e->getMessage(), $manageProcess->getBackupData()->getUmbrellaBackupId());
            $code = CodeResponse::BACKUP_ERROR;
        }

        wp_umbrella_get_service('TaskBackupLogger')->info("Code response: {$code}", $manageProcess->getBackupData()->getUmbrellaBackupId());

        switch($code) {
            case CodeResponse::BACKUP_NEXT_PART_FILES:
                $manageProcess->addSchedulerBatchFiles();
                break;
            case CodeResponse::BACKUP_FILES_FINISH:

                $data = $manageProcess->getBackupData();
                if ($data->getIsDatabaseSourceRequired()) {
                    $manageProcess->addSchedulerBatchDatabase();
                } else {
                    // finish
                    $manageProcess->addSchedulerCleanup();
                    $manageProcess->finishBackup();
                }
                break;
            case CodeResponse::BACKUP_ERROR:
                $data = $manageProcess->getBackupData();
                if ($data !== null) {
                    $data = $data->getData();
                } else {
                    $data = [];
                }

                $data['type'] = 'file';
                wp_umbrella_get_service('BackupApi')->postErrorBackup($data);
                $manageProcess->addSchedulerCleanup();
                break;
        }

        return $code !== CodeResponse::BACKUP_ERROR ? CodeResponse::SUCCESS : CodeResponse::BACKUP_ERROR;
    }
}
