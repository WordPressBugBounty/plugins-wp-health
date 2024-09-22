<?php
namespace WPUmbrella\Models\Backup;

class BackupProcessedData
{
    protected $data = [
        'backupId' => null,
        'incremental_date' => null,
        'file_source' => [
            'required' => false,
            'type' => '',
            'base_directory' => '',
        ],
        'sql_source' => [
            'required' => false,
            'type' => '',
            'database' => '',
            'database_connexion' => [
                'user' => '',
                'password' => '',
                'host' => '',
            ],
        ],
        'processor' => 'zip-php',
        'is_scheduled' => '1',
    ];

    public function createDefaultName($suffix = '', $database = false)
    {
        $title = str_replace(["%"], ["-"], sanitize_title(get_bloginfo('name')));
        $name = 'backup-';
        if ($database) {
            $name .= 'database-';
        }

        $value = date('Y-m-d-H');
        if ($this->data['backupId'] !== null) {
            $value = $this->data['backupId'];
        }

        $basename = sprintf('%s-%s', substr($title, 0, 5), $value);
        $name .= $basename;

        if (!empty($suffix)) {
            $name .= '-' . $suffix;
        }

        $part = $this->getPartBatchProcessor();
        if ($part !== null && !$database) {
            $name .= '-part-' . $part;
        }

        if ($database) {
            $this->data['sql_source']['name'] = sanitize_title($name);
        } else {
            $this->data['file_source']['name'] = sanitize_title($name);
        }

        return $this;
    }

    public function initData($data)
    {
        $isScheduled = isset($data['is_scheduled']) ? $data['is_scheduled'] : '1';

        $incrementalDate = null;
        if (isset($data['incremental_date'])) {
            $incrementalDate = $data['incremental_date'];
        }

        $mo = wp_umbrella_get_service('BackupFinderConfiguration')->getMaxMoBatchSize();
        $size = sprintf('<= %sM', $mo);
        if (isset($data['batch_processor']['size'])) {
            $size = $data['batch_processor']['size'];
        }

        $this->data = [
            'backupId' => isset($data['backupId']) ? $data['backupId'] : null,
            'suffix' => isset($data['suffix']) ? $data['suffix'] : '',
            'timestamp_start_date' => isset($data['timestamp_start_date']) ? $data['timestamp_start_date'] : time(),
            'timestamp_end_date' => isset($data['timestamp_end_date']) ? $data['timestamp_end_date'] : null,
            'incremental_date' => $incrementalDate,
            'processor' => 'zip-php',
            'is_scheduled' => $isScheduled,
            'file_source' => [
                'required' => false,
            ],
            'sql_source' => [
                'required' => false,
            ],
            'batch_processor' => [
                'max_file_batch_size' => isset($data['batch_processor']['max_file_batch_size']) ? $data['batch_processor']['max_file_batch_size'] : 1000,
                'max_mo_batch_size' => isset($data['batch_processor']['max_mo_batch_size']) ? $data['batch_processor']['max_mo_batch_size'] : wp_umbrella_get_service('BackupFinderConfiguration')->getMaxMoInBytesBatchSize(),
                'iterator_position' => isset($data['batch_processor']['iterator_position']) ? $data['batch_processor']['iterator_position'] : 0,
                'part' => isset($data['batch_processor']['part']) ? $data['batch_processor']['part'] : 1,
                'size' => $size,
            ],
            'batch_processor_sql' => [
                'iterator_position' => isset($data['batch_processor_sql']['iterator_position']) ? $data['batch_processor_sql']['iterator_position'] : 0,
                'part' => isset($data['batch_processor_sql']['part']) ? $data['batch_processor_sql']['part'] : 0,
            ],

            'snapshot' => isset($data['snapshot']) ? $data['snapshot'] : null,
        ];

        $filesRequired = isset($data['file_source']['required']) ? $data['file_source']['required'] : false;
        if ($filesRequired) {
            $this->setDefaultFileSource($data, $incrementalDate);
        }

        $sqlRequired = isset($data['sql_source']['required']) ? $data['sql_source']['required'] : false;
        if ($sqlRequired) {
            $this->setDefaultSqlSource($data);
        }

        $this->setWordPressData($data);

        if ($this->data['snapshot'] === null) {
            $this->setSnapshotData($data);
        }

        $this->suffix = isset($data['suffix']) ? $data['suffix'] : '';
        if ($incrementalDate !== null) {
            $this->suffix = sprintf('incremental-%s', $this->suffix);
        }

        return $this;
    }

    public function getSuffix()
    {
        return $this->data['suffix'];
    }

    public function setBackupId($id)
    {
        $this->data['backupId'] = $id;
    }

    public function setSnapshotData($data)
    {
        $this->data['snapshot'] = [
            'plugins' => wp_umbrella_get_service('PluginsProvider')->getPlugins(),
            'theme' => wp_umbrella_get_service('ThemesProvider')->getCurrentTheme(),
            'count_public_posts' => wp_umbrella_get_service('WordPressDataProvider')->countPosts(),
            'count_attachments' => wp_umbrella_get_service('WordPressDataProvider')->countAttachments()
        ];
    }

    public function setWordPressData($data)
    {
        $this->data['wordpress'] = isset($data['wordpress']) ? $data['wordpress'] : wp_umbrella_get_service('WordPressProvider')->getStateWordPress();
    }

    public function setDefaultFileSource($options, $incrementalDate = null)
    {
        $baseDirectory = isset($options['file_source']['base_directory']) ? $options['file_source']['base_directory'] : ABSPATH;
        $excludeFiles = isset($options['file_source']['exclude_files']) ? $options['file_source']['exclude_files'] : [];

        $mo = wp_umbrella_get_service('BackupFinderConfiguration')->getMaxMoBatchSize();

        $size = sprintf('<= %sM', $mo);
        $totalSize = null;
        if (isset($options['file_source']['total'])) {
            $totalSize = $options['file_source']['total'];
        } else {
            $totalSize = wp_umbrella_get_service('BackupFinderConfiguration')->countTotalFiles([
                'source' => $baseDirectory,
                'since_date' => $incrementalDate,
                'size' => $size,
                'exclude_files' => $excludeFiles
            ]);
        }

        $sizeOverload = sprintf('> %sM', $mo);
        $totalSizeOverload = null;
        if (isset($options['file_source']['total_overload'])) {
            $totalSizeOverload = $options['file_source']['total_overload'];
        } else {
            $totalSizeOverload = wp_umbrella_get_service('BackupFinderConfiguration')->countTotalFiles([
                'source' => $baseDirectory,
                'since_date' => $incrementalDate,
                'size' => $sizeOverload,
                'exclude_files' => $excludeFiles
            ]);
        }

        $this->data['file_source'] = [
            'exclude_files' => $excludeFiles,
            'name' => isset($options['file_source']['name']) ? $options['file_source']['name'] : '',
            'mode' => isset($options['file_source']['mode']) ? $options['file_source']['mode'] : 'normal',
            'zips_sent' => isset($options['file_source']['zips_sent']) ? $options['file_source']['zips_sent'] : [],
            'total' => $totalSize,
            'total_overload' => $totalSizeOverload,
            'required' => true,
            'type' => isset($options['file_source']['type']) ? $options['file_source']['type'] : 'finder-processor-batch',
            'size' => $size,
            'size_overload' => $sizeOverload,
            'base_directory' => $baseDirectory,
        ];
    }

    public function setMode($mode)
    {
        $this->data['file_source']['mode'] = $mode;
        return $this;
    }

    public function setFileSourceType($type)
    {
        $this->data['file_source']['type'] = $type;
        return $this;
    }

    public function getExcludeFiles()
    {
        return $this->data['file_source']['exclude_files'];
    }

    public function getMode()
    {
        return $this->data['file_source']['mode'];
    }

    public function setBatchSize($size)
    {
        $this->data['batch_processor']['size'] = $size;
        return $this;
    }

    public function getBatchSize()
    {
        return $this->data['batch_processor']['size'];
    }

    public function getSizeMoFileSource()
    {
        return $this->data['file_source']['size'];
    }

    public function getSizeMoFileSourceOverload()
    {
        return $this->data['file_source']['size_overload'];
    }

    public function setDefaultSqlSource($options)
    {
        $database = isset($options['database']) ? $options['database'] : DB_NAME;

        $excludeTables = isset($options['sql_source']['exclude_tables']) ? $options['sql_source']['exclude_tables'] : [];
        $total = isset($options['sql_source']['total']) ? $options['sql_source']['total'] : null;

        if ($total === null) {
            $tables = wp_umbrella_get_service('BackupDatabaseConfiguration')->getTablesConfiguration($excludeTables);
            $total = count($tables);
        }

        $host = DB_HOST;
        if (apply_filters('wp_umbrella_explode_host', true) && strpos($host, 'localhost') !== false && strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }

        $this->data['sql_source'] = [
            'required' => true,
            'name' => isset($options['sql_source']['name']) ? $options['sql_source']['name'] : '',
            'type' => 'mysqlmanual-by-table',
            'database' => $database,
            'total' => $total,
            'exclude_tables' => $excludeTables,
            'zips_sent' => isset($options['sql_source']['zips_sent']) ? $options['sql_source']['zips_sent'] : [],
            'database_connexion' => [
                'user' => DB_USER,
                'password' => DB_PASSWORD,
                'host' => $host
            ]
        ];

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getExcludeTables()
    {
        return $this->data['sql_source']['exclude_tables'];
    }

    public function getIncrementalDate()
    {
        return $this->data['incremental_date'];
    }

    public function getProcessor()
    {
        return $this->data['processor'];
    }

    public function getIsScheduled()
    {
        return $this->data['is_scheduled'];
    }

    public function getFileSource()
    {
        return $this->data['file_source'];
    }

    public function getBaseDirectory()
    {
        return $this->data['file_source']['base_directory'];
    }

    public function getSqlSource()
    {
        return $this->data['sql_source'];
    }

    public function getIsSqlSourceRequired()
    {
        return $this->data['sql_source']['required'];
    }

    public function getIsFileSourceRequired()
    {
        return $this->data['file_source']['required'];
    }

    public function getSqlSourceType()
    {
        return $this->data['sql_source']['type'];
    }

    public function getFileSourceType()
    {
        return $this->data['file_source']['type'];
    }

    public function getSqlSourceDatabase()
    {
        return $this->data['sql_source']['database'];
    }

    public function getSqlSourceDatabaseConnexion()
    {
        return $this->data['sql_source']['database_connexion'];
    }

    public function getSqlSourceDatabaseConnexionValue($key)
    {
        return $this->data['sql_source']['database_connexion'][$key];
    }

    public function getBuilderValueIsIncremetal()
    {
        return $this->data['incremental_date'] !== null ? '1' : '0';
    }

    public function setTimestampEndDate($time)
    {
        $this->data['timestamp_end_date'] = $time;
        return $this;
    }

    public function addFilenameZipSent($filename, $type)
    {
        if ($type === 'file') {
            $this->data['file_source']['zips_sent'][] = $filename;
        } else {
            $this->data['sql_source']['zips_sent'][] = $filename;
        }

        return $this;
    }

    protected function getMaxExecutionTime()
    {
        try {
            return @ini_get('max_execution_time');
        } catch (\Exception $e) {
            return 300;
        }
    }

    public function getMaxMoInBytesBatchSize()
    {
        return $this->data['batch_processor']['max_mo_batch_size'];
    }

    public function getMaxFilesByBatch()
    {
        return $this->data['batch_processor']['max_file_batch_size'];
    }

    public function getCurrentBatchProcessor()
    {
        return $this->data['batch_processor']['iterator_position'];
    }

    public function getCurrentBatchProcessorSql()
    {
        return $this->data['batch_processor_sql']['iterator_position'];
    }

    public function getPartBatchProcessor()
    {
        return $this->data['batch_processor']['part'];
    }

    public function setPartBatchProcessor($part)
    {
        $this->data['batch_processor']['part'] = $part;
        return $this;
    }

    public function setPartBatchSqlProcessor($part)
    {
        $this->data['batch_processor_sql']['part'] = $part;
        return $this;
    }

    public function getPartBatchSqlProcessor()
    {
        return $this->data['batch_processor_sql']['part'];
    }

    public function setIteratorPositionSql($position)
    {
        $this->data['batch_processor_sql']['iterator_position'] = $position;
        return $this;
    }

    public function setIteratorPosition($position)
    {
        $this->data['batch_processor']['iterator_position'] = $position;
        return $this;
    }

    public function getTotalFilesOverload()
    {
        return $this->data['file_source']['total_overload'];
    }

    public function getTotalFilesNormal()
    {
        return $this->data['file_source']['total'];
    }

    public function getTotalFiles()
    {
        return $this->getMode() === 'normal' ? $this->data['file_source']['total'] : $this->data['file_source']['total_overload'];
    }

    public function setTotalFiles($total)
    {
        $this->data['file_source']['total'] = $total;
        return $this;
    }

    public function getName($type = 'file')
    {
        if ($type == 'file') {
            return $this->data['file_source']['name'];
        }

        return $this->data['sql_source']['name'];
    }

    public function getBackupId()
    {
        return $this->data['backupId'];
    }

    public function getNameWithExtension($type)
    {
        $name = $this->getName($type);
        return sprintf('%s.zip', $name);
    }
}
