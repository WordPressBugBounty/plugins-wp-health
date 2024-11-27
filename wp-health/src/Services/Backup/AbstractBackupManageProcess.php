<?php
namespace WPUmbrella\Services\Backup;

use WPUmbrella\Models\Backup\BackupProcessedData;
use WPUmbrella\Core\Exceptions\BackupNotCreated;
use WPUmbrella\Services\Backup\BackupBatchData;
use WPUmbrella\Models\Backup\V2\BackupConfigData;
use WPUmbrella\Core\Constants\BackupData;
use ActionScheduler;
use ActionScheduler_Store;

abstract class AbstractBackupManageProcess
{
    protected $data;

    const FILE_CONFIG = 'config.php';

    public function getPathFileConfig()
    {
        return sprintf('%s/%s', WP_UMBRELLA_DIR_WPU_BACKUP, self::FILE_CONFIG);
    }

    public function isWritable()
    {
        return is_writable(WP_UMBRELLA_DIR_WPU_BACKUP_BOX);
    }

    public function finishBackup()
    {
    }

    public function generateDirectorySuffix()
    {
        $directorySuffix = sprintf('umbrella-%s', bin2hex(random_bytes(5)));
        update_option('wp_umbrella_backup_suffix_security', $directorySuffix, false);
        return $directorySuffix;
    }

    /**
     * @param array $params
     * 	- string $params['title']
     * 	- string $params['suffix']
     * 	- string $params['date']
     * 	- string $params['database']
     * 	- string $params['part']
     * 	- string $params['backupId']
     *
     * @return string
     */
    public function createDefaultName($params)
    {
        $title = $params['title'] ?? '';
        $suffix = $params['suffix'] ?? '';
        $database = $params['database'] ?? false;
        $part = $params['part'] ?? null;
        $backupId = $params['backupId'] ?? null;

        $name = 'backup-';
        if ($database) {
            $name .= 'database-';
        }

        date_default_timezone_set('UTC');
        $value = $params['date'] ?? @date('Y-m-d-H');
        if ($backupId !== null) {
            $value = $backupId;
        }

        $basename = sprintf('%s-%s', substr($title, 0, 5), $value);
        $name .= $basename;

        if (!empty($suffix)) {
            $name .= '-' . $suffix;
        }

        if ($part !== null && !$database) {
            $name .= '-part-' . $part;
        }

        return strtolower($name);
    }

    protected function getInitDefaultData($params)
    {
        $moPerFile = $params['max_mo_size_per_file'] ?? wp_umbrella_get_service('BackupFinderConfiguration')->getMaxMoPerFileSize();
        $size = sprintf('<= %sM', $moPerFile);

        if (isset($params['max_file_mo_size'])) { // Max File Mo Size is use by BatchSize
            $moBatchSize = $params['max_file_mo_size'];
        } else {
            $moBatchSize = wp_umbrella_get_service('BackupFinderConfiguration')->getMaxMoBatchSize();
        }

        $maxSizeInBytes = wp_umbrella_get_service('BackupFinderConfiguration')->getMaxMoInBytesBatchSize($moBatchSize);

        $host = wp_umbrella_get_service('WordPressContext')->getDbHost();
        // Prevent DB_HOST with port
        if (
            apply_filters('wp_umbrella_explode_host', true) &&
            (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ARGOS') !== false) &&
            strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }

        $bySock = apply_filters('wp_umbrella_connect_by_sock', false);
        $sockValue = null;
        if ($bySock) {
            $sockValue = apply_filters('wp_umbrella_connect_by_sock_host', '');
            if (strpos(DB_HOST, '.sock') !== false && empty($sockValue)) {
                // eg: localhost:/var/run/mysqld/mysqld.sock
                $dataSockExplode = explode(':', DB_HOST);
                $sockValue = isset($dataSockExplode[1]) ? $dataSockExplode[1] : '';
            }
        }

        $suffix = isset($params['suffix']) ? $params['suffix'] : '';
        $isScheduled = isset($params['is_scheduled']) ? $params['is_scheduled'] : '1';

        $title = '';
        try {
            if (function_exists('parse_url') && function_exists('site_url')) {
                $url = parse_url(site_url());
                if (isset($url['host'])) {
                    $title = sanitize_title($url['host']);
                }
            }

            if (empty($title)) {
                $title = str_replace(['%'], ['-'], sanitize_title(get_bloginfo('name')));
            }
        } catch (\Exception $e) {
            $title = str_shuffle('abcdefghijklmnopqrstuvwxyz');
        }

        date_default_timezone_set('UTC');
        $date = date('Y-m-d-H');

        $nameBackupDatabase = $this->createDefaultName([
            'title' => $title,
            'suffix' => $suffix,
            'date' => $date,
            'database' => true
        ]);

        $nameBackupFile = $this->createDefaultName([
            'title' => $title,
            'date' => $date,
            'suffix' => $suffix,
        ]);

        $excludeTables = isset($params['exclude_tables']) ? $params['exclude_tables'] : [];

        $highTables = wp_umbrella_get_service('BackupDatabaseConfigurationV2')->getHighDataTables();

        $memoryLimit = wp_umbrella_get_service('WordPressProvider')->getMemoryLimitBytes();
        $dividedBy = $params['memory_limit_divided_by'] ?? BackupData::DEFAULT_MEMORY_LIMIT_DIVIDED_BY;
        $maximumLinesByBatch = $params['maximum_lines_by_batch'] ?? BackupData::MAXIMUM_LINES_BY_BATCH;

        $tablesWithRestrictedDensity = isset($params['tables_with_restricted_density']) ? $params['tables_with_restricted_density'] : [];

        $tables = wp_umbrella_get_service('BackupDatabaseConfigurationV2')->getTablesConfiguration($excludeTables, [
            'memory_limit_divided_by' => $dividedBy,
            'maximum_lines_by_batch' => $maximumLinesByBatch,
            'tables_with_restricted_density' => $tablesWithRestrictedDensity,
            'high_tables' => $highTables
        ]);

        $defaultSourceBaseDirectory = wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource();
        $baseDirectory = isset($params['base_directory']) ? $params['base_directory'] : $defaultSourceBaseDirectory;
        if (empty($baseDirectory)) {
            $baseDirectory = $defaultSourceBaseDirectory;
        }

        $checksum = isset($params['checksum']) ? $params['checksum'] : [];

        $currentChecksum = [];
        if ((int) $isScheduled === 1) {
            $tablesByColumns = array_column($tables, 'name');

            $tablesName = array_map(function ($item) {
                return $item['name'];
            }, $tables);

            $currentChecksum = wp_umbrella_get_service('DatabaseTablesProvider')->getTablesChecksum([
                'tables' => $tablesName,
            ]);

            foreach ($tablesName as $tableName) {
                if (!isset($checksum[$tableName])) {
                    continue;
                }

                if (!isset($currentChecksum[$tableName])) {
                    continue;
                }

                if ($currentChecksum[$tableName] === $checksum[$tableName]) {
                    $index = array_search($tableName, $tablesByColumns);
                    if ($index !== false) {
                        unset($tables[$index]);
                    }
                }
            }

            $tables = array_values($tables);
        }

        $excludeFiles = isset($params['exclude_files']) ? $params['exclude_files'] : [];
        $preventPathNotAllowed = wp_umbrella_get_service('PreventErrorOnPathNotAllowed')->getInaccessiblePaths();
        $excludeFiles = array_merge($excludeFiles, $preventPathNotAllowed);

        return  [
            'title' => $title,
            'date' => $date,
            'suffix' => $suffix,
            'is_scheduled' => $isScheduled,
            'update_data_method' => isset($params['update_data_method']) ? $params['update_data_method'] : 'database', // file | database
            'backupId' => isset($params['backupId']) ? $params['backupId'] : null,
            'incremental_date' => isset($params['incremental_date']) ? $params['incremental_date'] : null,
            'timestamp_start_date' => time(),
            'file' => [
                'finish' => false,
                'name' => $nameBackupFile,
                'mode' => 'normal',
                'required' => isset($params['files']) ? $params['files'] : true,
                'base_directory' => $baseDirectory,
                'exclude' => $excludeFiles,
                'zips_sent' => [],
                'batch' => [
                    'iterator_position' => 0, // Offset
                    'total' => null,
                    'max_size' => $maxSizeInBytes,
                    'part' => 1,
                    'size' => $size,
                ],
            ],
            'database' => [
                'finish' => false,
                'name' => $nameBackupDatabase,
                'required' => isset($params['sql']) ? $params['sql'] : true,
                'exclude' => $excludeTables,
                'zips_sent' => [],
                'tables' => $tables,
                'high_tables' => $highTables,
                'connection' => [
                    'database' => DB_NAME,
                    'user' => DB_USER,
                    'password' => DB_PASSWORD,
                    'host' => $host,
                    'sock' => $sockValue
                ],
                'batch' => [
                    'memory_limit_divided_by' => $dividedBy,
                    'maximum_lines_by_batch' => $maximumLinesByBatch,
                    'maximum_memory_authorized' => $memoryLimit / $dividedBy,
                    'iterator_position' => 0, // Offset for table
                    'part' => 0, // Part for table_batchs
                ],
                'table_batchs' => []
            ],
            'checksum' => $currentChecksum,
            'snapshot' => [
                'plugins' => array_map(function ($item) {
                    return $item->getPropertiesValues();
                }, wp_umbrella_get_service('PluginsProvider')->getPlugins([
                    'light' => true
                ])),
                'theme' => wp_umbrella_get_service('ThemesProvider')->getCurrentTheme(),
                'count_public_posts' => wp_umbrella_get_service('WordPressDataProvider')->countPosts(),
                'count_attachments' => wp_umbrella_get_service('WordPressDataProvider')->countAttachments()
            ],
            'wordpress' => wp_umbrella_get_service('WordPressProvider')->getStateWordPress()
        ];
    }

    /**
     * Init config data for backup
     * @param array $params
     */
    protected function initData($params)
    {
        $data = $this->getInitDefaultData($params);

        $directorySuffix = $this->generateDirectorySuffix();

        wp_mkdir_p(sprintf('%s/%s', WP_UMBRELLA_DIR_WPU_BACKUP_BOX, $directorySuffix));

        return apply_filters('wp_umbrella_get_data_init_backup', $data);
    }

    public function isWritableData()
    {
        try {
            return is_writable(dirname($this->getPathFileConfig()));
        } catch (\Exception $e) {
            return true;
        }
    }

    public function init($params)
    {
        if ($this->isBackupInProgress()) {
            throw new BackupNotCreated('Backup in progress');
        }

        $data = $this->initData($params);

        $backupId = isset($params['backupId']) ? $params['backupId'] : null;

        if ($backupId === null) {
            $backupData = wp_umbrella_get_service('BackupApi')->postInitBackup($data);

            if ($backupData === null || !isset($backupData['id'])) {
                throw new BackupNotCreated('Error Processing Create Backup');
            }

            $data['backupId'] = $backupData['id'];
        }

        // Clean old process if necesary
        wp_umbrella_get_service('BackupExecutorV2')->cleanup();

        return $data;
    }

    public function backupDoesHaveActionInProgress() : bool
    {
        throw new Exception('Method backupDoesHaveActionInProgress not implemented');
    }

    public function isBackupInProgress() : bool
    {
        throw new Exception('Method isBackupInProgress not implemented');
    }

    public function deleteProcess()
    {
        throw new Exception('Method deleteProcess not implemented');
    }

    public function getBackupData()
    {
        throw new Exception('Method getBackupData not implemented');
    }

    abstract protected function getUpdateBackupDataMethod($type);

    public function updateBackupData($data)
    {
        throw new Exception('Method updateBackupData not implemented');
    }

    public function addSchedulerBatchFiles()
    {
        throw new Exception('Method addSchedulerBatchFiles not implemented');
    }

    public function addSchedulerBatchDatabase()
    {
        throw new Exception('Method addSchedulerBatchDatabase not implemented');
    }

    public function addSchedulerPrepareBatchDatabase()
    {
        throw new Exception('Method addSchedulerPrepareBatchDatabase not implemented');
    }

    public function addSchedulerCleanup()
    {
        throw new Exception('Method addSchedulerCleanup not implemented');
    }

    public function addSchedulerCheckBatchDatabase()
    {
        throw new Exception('Method addSchedulerCheckBatchDatabase not implemented');
    }

    public function unscheduledBatch()
    {
        $this->deleteProcess();
        wp_umbrella_get_service('BackupExecutorV2')->cleanup();
    }
}
