<?php
namespace WPUmbrella\Services\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupBuilder as BackupBuilderModel;
use WPUmbrella\Models\Backup\BackupProcessedData;

class BackupDirector
{
    /**
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupProfile
     */
    public function constructBackupProfileOnlyFiles(BackupBuilderModel $builder, BackupProcessedData $processedData)
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $processedData, 'file');

        if ($processedData->getIsFileSourceRequired()) {
            $builder = $this->buildFileSource($builder, $processedData);
        }

        $builder->buildProfile();

        return $builder->getProfile();
    }

    /**
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupProfile
     */
    public function constructBackupProfileOnlySQL(BackupBuilderModel $builder, BackupProcessedData $processedData)
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $processedData, 'database');

        if ($processedData->getIsSqlSourceRequired()) {
            $builder = $this->buildSqlSource($builder, $processedData);
        }

        $builder->buildProfile();

        return $builder->getProfile();
    }

    /**
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupProfile
     */
    public function constructBackupProfileDestination(BackupBuilderModel $builder, BackupProcessedData $processedData, $type = 'file')
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $processedData, $type);
        $builder = $this->buildProcessor($builder, $processedData);
        $builder = $this->buildDestinations($builder, $processedData);

        $builder->buildProfile();

        return $builder->getProfile();
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupBuilderModel
     */
    protected function buildFileSource(BackupBuilderModel $builder, BackupProcessedData $processedData)
    {
        $builder->buildFileSource([
            'type' => $processedData->getFileSourceType(),
            'base_directory' => $processedData->getBaseDirectory(),
            'incremental_date' => $processedData->getIncrementalDate(),
            'size' => $processedData->getBatchSize(),
            'mode' => $processedData->getMode(),
        ]);
        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupBuilderModel
     */
    protected function buildSqlSource(BackupBuilderModel $builder, BackupProcessedData $processedData)
    {
        $builder->buildSqlSource([
            'type' => $processedData->getSqlSourceType(),
            'database' => $processedData->getSqlSourceDatabase(),
            'user' => $processedData->getSqlSourceDatabaseConnexionValue('user'),
            'password' => $processedData->getSqlSourceDatabaseConnexionValue('password'),
            'host' => $processedData->getSqlSourceDatabaseConnexionValue('host'),
            'sock' => $processedData->getSqlSourceDatabaseConnexionValue('sock'),
        ]);

        $builder = apply_filters('wp_umbrella_director_build_destination', $builder, $processedData);

        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupBuilderModel
     */
    protected function buildNamer(BackupBuilderModel $builder, BackupProcessedData $processedData, $type = 'file')
    {
        $builder->buildNamer($processedData->getName($type));
        return apply_filters('wp_umbrella_director_get_build_namer', $builder);
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupBuilderModel
     */
    protected function buildProcessor(BackupBuilderModel $builder, BackupProcessedData $processedData)
    {
        $builder->buildProcessor([
            'type' => $processedData->getProcessor(),
        ]);

        $builder = apply_filters('wp_umbrella_director_build_processor', $builder, $processedData);

        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupBuilderModel
     */
    protected function buildDestinations(BackupBuilderModel $builder, BackupProcessedData $processedData)
    {
        $builder->buildDestination([
            'incremental' => $processedData->getBuilderValueIsIncremetal(),
            'is_scheduled' => $processedData->getIsScheduled(),
        ]);

        $builder = apply_filters('wp_umbrella_director_build_destination', $builder, $processedData);

        return $builder;
    }

    /**
     * @param BackupBuilderModel $builder
     * @param BackupProcessedData $processedData
     * @return BackupProfile
     */
    public function constructBackupProfileProcessor(BackupBuilderModel $builder, BackupProcessedData $processedData, $type = 'file')
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $processedData, $type);
        $builder = $this->buildProcessor($builder, $processedData);
        $builder->buildProfile();

        return $builder->getProfile();
    }
}
