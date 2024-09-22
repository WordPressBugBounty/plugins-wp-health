<?php
namespace WPUmbrella\Services\Backup\QueueRunner\V2;

use WPUmbrella\Core\Backup\Builder\V2\BackupBuilder;
use WPUmbrella\Services\Backup\V2\BackupDirector;
use WPUmbrella\Helpers\DataTemporary;
use WPUmbrella\Core\Constants\CodeResponse;

class BackupQueueRunnerFiles extends AbstractBackupQueue
{
    const TYPE = 'file';

    const NAME_SERVICE = 'BackupQueueRunnerFilesV2';

    public function run($options = [])
    {
        $version = $options['version'] ?? 'v1';

        $manageProcess = $this->getManageProcessByVersion($version);

        $data = $manageProcess->getBackupData();

        if ($data === null || !$data->existDatabaseData()) { // Exist database needed for prevent fail update_option
            throw new \Exception('Error processing backup files with no backup data');
        }

        $finish = $data->getFinish(self::TYPE);

        if ($finish) {
            return [
                'success' => true,
                'data' => [
                    'code' => CodeResponse::BACKUP_FILES_FINISH
                ]
            ];
        }

        $name = $manageProcess->createDefaultName([
            'title' => $data->getTitle(),
            'suffix' => $data->getSuffix(),
            'database' => false,
            'part' => $data->getBatchPart(self::TYPE),
            'backupId' => $data->getBackupId()
        ]);

        $data->setName($name, self::TYPE);

        /**
         * @var BackupDirector
         */
        $backupDirector = wp_umbrella_get_service('BackupDirectorV2');
        $builder = new BackupBuilder();

        $currentIterator = $data->getBatchIterator(self::TYPE);

        // Only backup files
        $profile = $backupDirector->constructBackupProfileOnlyFiles($builder, $data);

        $backupExecutor = wp_umbrella_get_service('BackupExecutorV2');
        $result = $backupExecutor->backupSources($profile);

        if (!isset($result[0])) {
            return [
                'success' => false,
                'data' => [
                    'code' => CodeResponse::BACKUP_ERROR
                ]
            ];
        }

        $response = $result[0];

        if (!$response['success']) {
            return [
                'success' => false,
                'data' => [
                    'code' => CodeResponse::BACKUP_ERROR
                ]
            ];
        }

        // Send to destinations if necessary
        if ($response['count_iterator'] !== 0) {
            $this->sendToDestinations($data, self::TYPE);
        }

        $newPosition = $response['iterator_position'];

        $iteratorException = DataTemporary::getDataByKey('iterator_exception');
        if ($iteratorException !== null) {
            $newPosition += $iteratorException;
        }

        // Prevent while true
        if ($newPosition === $currentIterator) {
            $newPosition++;
        } elseif ($newPosition < $currentIterator) {
            $newPosition = $currentIterator + 2;
        }

        $data->setBatchIterator(self::TYPE, $newPosition);

        // Necessary for change name zip need upload
        $data->setBatchPart(self::TYPE, (int) $data->getBatchPart('file') + 1);

        $manageProcess->updateBackupData($data->getData());

        $batch = $data->getBatch(self::TYPE);

        if ($response['count_iterator'] === 0 && $batch['total'] === null) {
            @set_time_limit(0);

            try {
                $batch['total'] = wp_umbrella_get_service('BackupFinderConfiguration')->countTotalFiles(
                    [
                        'exclude_files' => $data->getExcludeFiles(),
                        'since_date' => $data->getIncrementalDate(),
                        'size' => $data->getBatchSize(),
                        'source' => $data->getBaseDirectory()
                    ]
                );
            } catch (\Exception $e) {
                $batch['total'] = WP_UMBRELLA_MAX_PRIORITY_HOOK; // Fake total
            }

            $data->setBatch(self::TYPE, $batch);
        }

        if ($response['count_iterator'] === 0 && $batch['total'] !== null && $newPosition >= $batch['total']) { // Finish backup files
            try {
                $data->setFinish(self::TYPE);
                $manageProcess->updateBackupData($data->getData());

                if ($version === 'v3') {
                    wp_umbrella_get_service('BackupRepository')->setFinishByType(
                        $data->getUmbrellaBackupId(),
                        self::TYPE
                    );
                }

                $this->finishBackupAndSaveDataModel($data, self::TYPE);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'data' => [
                        'code' => CodeResponse::BACKUP_ERROR
                    ]
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'code' => CodeResponse::BACKUP_FILES_FINISH
                ]
            ];
        } else {
            $this->saveCurrentDataModel($data, self::TYPE);
        }

        return [
            'success' => true,
            'data' => [
                'code' => CodeResponse::BACKUP_NEXT_PART_FILES,
            ]
        ];
    }
}
