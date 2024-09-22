<?php
namespace WPUmbrella\Services\Backup\V2;

use WPUmbrella\Models\Backup\BackupBuilder as BackupBuilderModel;
use WPUmbrella\Models\Backup\V2\BackupConfigData;

class BackupDirector
{
    const NAME_SERVICE = 'BackupDirectorV2';

    /**
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupProfile
     */
    public function constructBackupProfileOnlyFiles(BackupBuilderModel $builder, BackupConfigData $data)
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $data, 'file');
        $builder = $this->buildFileSource($builder, $data);

        $builder->buildProfile();

        return $builder->getProfile();
    }

    /**
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupProfile
     */
    public function constructBackupProfileOnlySQL(BackupBuilderModel $builder, BackupConfigData $data)
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $data, 'database');
        $builder = $this->buildSqlSource($builder, $data);

        $builder->buildProfile();

        return $builder->getProfile();
    }

    /**
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupProfile
     */
    public function constructBackupProfileDestination(BackupBuilderModel $builder, $data, $options = [])
    {
        $type = isset($options['type']) ? $options['type'] : 'file';
        $api = isset($options['api']) ? $options['api'] : null;

        $builder->reset();

        $builder = $this->buildNamer($builder, $data, $type);
        $builder = $this->buildProcessor($builder, $data);
        $builder = $this->buildDestinations($builder, $api);

        $builder->buildProfile();

        return $builder->getProfile();
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupBuilderModel
     */
    protected function buildFileSource(BackupBuilderModel $builder, BackupConfigData $data)
    {
        $builder->buildFileSource([
            'base_directory' => $data->getBaseDirectory(),
            'incremental_date' => $data->getIncrementalDate(),
            'size' => $data->getBatchSize(),
            'mode' => $data->getMode(),
            'version' => $data->getVersion(),
        ]);
        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupBuilderModel
     */
    protected function buildSqlSource(BackupBuilderModel $builder, BackupConfigData $data)
    {
        $builder->buildSqlSource([
            'database' => $data->getDatabaseValue('database'),
            'user' => $data->getDatabaseValue('user'),
            'password' => $data->getDatabaseValue('password'),
            'host' => $data->getDatabaseValue('host'),
            'version' => $data->getVersion(),
            'sock' => $data->getDatabaseValue('sock'),
        ]);

        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupBuilderModel
     */
    protected function buildNamer(BackupBuilderModel $builder, BackupConfigData $data, $type = 'file')
    {
        $builder->buildNamer($data->getName($type));

        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupBuilderModel
     */
    protected function buildProcessor(BackupBuilderModel $builder, BackupConfigData $data)
    {
        $builder->buildProcessor();

        return $builder;
    }

    /**
     *
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupBuilderModel
     */
    protected function buildDestinations(BackupBuilderModel $builder, $api = null)
    {
        $builder->buildDestination([
            'api' => $api
        ]);

        return $builder;
    }

    /**
     * @param BackupBuilderModel $builder
     * @param BackupConfigData $data
     * @return BackupProfile
     */
    public function constructBackupProfileProcessor(BackupBuilderModel $builder, BackupConfigData $data, $type = 'file')
    {
        $builder->reset();

        $builder = $this->buildNamer($builder, $data, $type);
        $builder = $this->buildProcessor($builder, $data);
        $builder->buildProfile();

        return $builder->getProfile();
    }
}
