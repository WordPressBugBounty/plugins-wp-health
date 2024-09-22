<?php
namespace WPUmbrella\Services\Backup\V2;

use WPUmbrella\Core\Constants\BackupData;

class BackupDatabaseConfiguration
{
    const NAME_SERVICE = 'BackupDatabaseConfigurationV2';

    public function getHighDataTables()
    {
        global $wpdb;

        return apply_filters('wp_umbrella_get_high_data_tables', [
            "{$wpdb->prefix}posts",
            "{$wpdb->prefix}postmeta",
        ]);
    }

    public function getSmallBatchSizeTables()
    {
        global $wpdb;

        $defaultSmallBatch = [
            "{$wpdb->prefix}woocommerce_log",
            "{$wpdb->prefix}fsmpt_email_logs",
            "{$wpdb->prefix}email_log",
            "{$wpdb->prefix}oses_emails",
            "{$wpdb->prefix}lpc_outward_label",
            "{$wpdb->prefix}wpml_mails",
            "{$wpdb->prefix}amelia_notifications_log",
            "{$wpdb->prefix}bookly_log",
        ];

        // We need to do this because some installation remove data
        $data = apply_filters('wp_umbrella_backup_small_batch_size_tables', $defaultSmallBatch);

        if ($data === null || !is_array($data)) {
            return $defaultSmallBatch;
        }

        return $data;
    }

    public function getDefaultExcludeTables()
    {
        global $wpdb;
        $tableList = wp_umbrella_get_service('TableList');

        $tableLog = $wpdb->prefix . $tableList->getTableLog()->getName();
        $tableBackup = $wpdb->prefix . $tableList->getTableBackup()->getName();
        $tableTaskBackup = $wpdb->prefix . $tableList->getTableTaskBackup()->getName();
        $tableWooCommerceLog = $wpdb->prefix . 'woocommerce_log';

        return apply_filters('wp_umbrella_backup_default_exclude_tables', [
            $tableLog,
            $tableBackup,
            $tableTaskBackup,
            $tableWooCommerceLog,
        ]);
    }

    /**
     * @param array $excludeTables
     * @param $options array
     *   - memory_limit_divided_by int
     *   - maximum_lines_by_batch int
     *   - high_tables array
     * @return array
     */
    public function getTablesConfiguration($excludeTables = [], $options = [])
    {
        $defaultExcludeTables = $this->getDefaultExcludeTables();
        $excludeTables = array_merge($excludeTables, $defaultExcludeTables);

        $tables = wp_umbrella_get_service('DatabaseTablesProvider')->getTablesWithSize();

        foreach ($tables as $key => $table) {
            if (in_array($table['name'], $excludeTables, true)) {
                unset($tables[$key]);
                continue;
            }
        }

        return $this->getBatchForTables($tables, $options);
    }

    /**
     * @param array $tables
     * @param $options array
     *   - memory_limit_divided_by int
     *   - maximum_lines_by_batch int
     *   - high_tables array
     *   - tables_with_restricted_density array[table_name, maximum_lines_by_batch]
     * @return array
     */
    public function getBatchForTables($tables, $options)
    {
        $smallBatchSizes = $this->getSmallBatchSizeTables();

        foreach ($tables as $key => $table) {
            $optionsForTable = $options;

            // Just check small batch sizes (generally log table)
            if (in_array($table['name'], $smallBatchSizes)) {
                $maxSize = apply_filters('wp_umbrella_backup_small_batch_size_tables_max_size', 6000);
                if ($maxSize === null || $maxSize > 6000) {
                    $maxSize = 6000;
                }
                $optionsForTable['maximum_lines_by_batch'] = $maxSize;
                $tables[$key]['maximum_lines_by_batch'] = $maxSize;
            }

            if (!empty($options['tables_with_restricted_density'])) {
                $itemTable = array_search($table['name'], array_column($options['tables_with_restricted_density'], 'table_name'));

                if ($itemTable !== false) {
                    $maximumLinesByBatch = $options['tables_with_restricted_density'][$itemTable]['maximum_lines_by_batch'] ?? $options['maximum_lines_by_batch'];

                    $optionsForTable['maximum_lines_by_batch'] = $maximumLinesByBatch;
                    $tables[$key]['maximum_lines_by_batch'] = $maximumLinesByBatch;
                }
            }

            $tables[$key]['need_batch'] = $this->getTableNeedBatch($table, $optionsForTable);
        }

        return array_values($tables);
    }

    /**
     * @param array $table
     * 		- name string
     * 		- size int
     * 		- rows int
     *
     * @param $options array
     *  - memory_limit_divided_by int
     *  - maximum_lines_by_batch int
     * @return boolean
     */
    public function getTableNeedBatch($table, $options)
    {
        $memoryLimit = wp_umbrella_get_service('WordPressProvider')->getMemoryLimitBytes();

        $dividedBy = $options['memory_limit_divided_by'] ?? BackupData::DEFAULT_MEMORY_LIMIT_DIVIDED_BY;
        $maximumLinesByBatch = $options['maximum_lines_by_batch'] ?? BackupData::MAXIMUM_LINES_BY_BATCH;

        // Check rows by table first
        if (isset($table['rows']) && $table['rows'] > $maximumLinesByBatch) {
            return true;
        }

        $maximumMemoryAuthorize = $memoryLimit / $dividedBy; // Bytes

        if ($table['size'] <= $maximumMemoryAuthorize) {
            return false;
        }

        return true;
    }

    /**
     * @param array $table
     *  - name string
     *  - columns array
     * @param array $batch
     * @param array $options
     * 	- offset int
     * 	- total int
     *  - maximum_memory int
     */
    protected function prepareBatchsRecursively($table, $options, $batchs = [])
    {
        @set_time_limit(0);

        $maximumMemory = $options['maximum_memory'];

        $provider = wp_umbrella_get_service('DatabaseTablesProvider');

        $offset = $options['offset'] ?? 0;
        $total = $options['total'];

        $offsetLeft = $offset;
        $limitLeft = $limitRight = ceil(($total - $offset) / 2);

        $totalSizeLeft = $provider->getSizeOfLines($table['name'], $table['columns'], [
            'offset' => $offsetLeft,
            'limit' => $limitLeft,
        ]);

        $offsetRight = $offsetLeft + $limitLeft;

        $totalSizeRight = $provider->getSizeOfLines($table['name'], $table['columns'], [
            'offset' => $offsetRight,
            'limit' => $limitRight,
        ]);

        if ($totalSizeLeft >= $maximumMemory) {
            $newBatchsLeft = $this->prepareBatchsRecursively($table, [
                'offset' => $offsetLeft,
                'total' => $offsetLeft + $limitLeft,
                'maximum_memory' => $maximumMemory,
            ], $batchs);

            $batchs = $newBatchsLeft;
        } else {
            $batchs[] = [
                'offset' => (int) $offsetLeft,
                'limit' => (int) $limitLeft,
                'need_check' => false
            ];
        }

        if ($totalSizeRight >= $maximumMemory) {
            $newBatchRight = $this->prepareBatchsRecursively($table, [
                'offset' => $offsetRight,
                'total' => $offsetRight + $limitRight,
                'maximum_memory' => $maximumMemory,
            ], $batchs);

            $batchs = $newBatchRight;
        } else {
            $batchs[] = [
                'offset' => (int) $offsetRight,
                'limit' => (int) $limitRight,
                'need_check' => false
            ];
        }
        return $batchs;
    }

    /**
     * @param $table array
     * 	 - name string
     *   - columns array
     * @param $options array
     *   - maximum_memory int
     * @param $batchs array
     *
     * @return array
     */
    public function preventInitBatchs($table, $options, $batchs)
    {
        $maximumMemory = $options['maximum_memory'];
        $provider = wp_umbrella_get_service('DatabaseTablesProvider');

        foreach ($batchs as $key => $batch) {
            $totalSize = $provider->getSizeOfLines($table['name'], $table['columns'], [
                'offset' => $batch['offset'],
                'limit' => $batch['limit'],
            ]);

            if ($totalSize >= $maximumMemory) {
                $newBatchs = $this->prepareBatchsRecursively($table, [
                    'offset' => $batch['offset'],
                    'total' => $batch['offset'] + $batch['limit'],
                    'maximum_memory' => $maximumMemory,
                ]);

                unset($batchs[$key]);
                $batchs = array_merge($batchs, $newBatchs);
            } else {
                $batchs[$key]['need_check'] = false;
            }
        }

        return $batchs;
    }

    /**
     * @param $table array
     * 	 - table string
     *   - size int
     *   - maximum_lines_by_batch int
     *   - rows int
     * @param $options array
     *   - memory_limit_divided_by int
     *   - high_tables array
     *
     * @return array
     */
    public function getBatchForTable($table, $options = []): array
    {
        $memoryLimit = wp_umbrella_get_service('WordPressProvider')->getMemoryLimitBytes();

        // Default options
        $dividedBy = $options['memory_limit_divided_by'] ?? BackupData::DEFAULT_MEMORY_LIMIT_DIVIDED_BY;
        $maximumLinesByBatch = $options['maximum_lines_by_batch'] ?? BackupData::MAXIMUM_LINES_BY_BATCH;

        if (isset($table['maximum_lines_by_batch'])) {
            $maximumLinesByBatch = (int) $table['maximum_lines_by_batch'];
        }

        $maximumMemoryAuthorize = $memoryLimit / $dividedBy; // Bytes

        $provider = wp_umbrella_get_service('DatabaseTablesProvider');
        $total = $provider->getCountRows($table['name']);

        $batchs = [];

        if ($total > $maximumLinesByBatch) {
            $numberBatchs = ceil($total / $maximumLinesByBatch);

            for ($i = 0; $i < $numberBatchs; $i++) {
                $batchs[] = [
                    'offset' => $i * $maximumLinesByBatch,
                    'limit' => $maximumLinesByBatch,
                    'need_check' => true
                ];
            }
        } else {
            $batchs = $this->prepareBatchsRecursively($table, [
                'limit' => ceil($total / 2),
                'total' => $total,
                'maximum_memory' => $maximumMemoryAuthorize,
            ]);
        }

        return $batchs;
    }
}
