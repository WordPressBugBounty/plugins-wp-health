<?php
namespace WPUmbrella\Services\Backup;

if (!defined('ABSPATH')) {
    exit;
}

class BackupDatabaseConfiguration
{
    const MO_IN_BYTES = 1048576;

    protected function getHighDataTables()
    {
        global $wpdb;

        return apply_filters('wp_umbrella_get_high_data_tables', [
            "{$wpdb->prefix}posts",
            "{$wpdb->prefix}postmeta",
        ]);
    }

    public function getTablesConfiguration($excludeTables = [])
    {
        $data = wp_umbrella_get_service('DatabaseTablesProvider')->getTablesSize();

        foreach ($data as $key => $value) {
            if (in_array($value['table'], $excludeTables, true)) {
                unset($data[$key]);
                continue;
            }
        }

        return $data;
    }

    public function getTablesBatch(): array
    {
        $memoryLimit = wp_umbrella_get_service('WordPressProvider')->getMemoryLimitBytes();

        $maximumMemory = apply_filters('wp_umbrella_maximum_memory_limit_database', $memoryLimit / 4); // Bytes
        $maximumMemoryMo = $maximumMemory / self::MO_IN_BYTES; // Mo

        $objDatabase = wp_umbrella_get_service('BackupBatchData')->getData('database');
        if ($objDatabase === null) {
            $excludeTables = [];
        } else {
            $excludeTables = $objDatabase->getExcludeTables();
        }

        $defaultExcludeTables = $this->getDefaultExcludeTables();
        $excludeTables = array_merge($excludeTables, $defaultExcludeTables);

        $data = $this->getTablesConfiguration($excludeTables);
        $optimizeByLine = apply_filters('wp_umbrella_backup_database_by_line', false);

        $highTables = $this->getHighDataTables();
        foreach ($data as $key => $value) {
            $data[$key]['index'] = $key;

            if ((int) $value['size_mb'] <= $maximumMemoryMo) {
                continue;
            }

            $isHighTable = in_array($value['table'], $highTables, true);

            // Optimize by size
            // If the table is not in the high tables list, we optimize it by size
            if (!$isHighTable || !$optimizeByLine) {
                $rows = wp_umbrella_get_service('DatabaseTablesProvider')->getCountRows($value['table']);

                $numberBatch = ceil($value['size_mb'] / $maximumMemoryMo);
                $maximumRowsByBatch = ceil($rows / $numberBatch);

                $batch = [];
                for ($i = 0; $i < $numberBatch; $i++) {
                    $batch[] = [
                        'offset' => $i * $maximumRowsByBatch,
                        'limit' => $maximumRowsByBatch,
                    ];
                }
                $value['batch'] = $batch;
                $data[$key] = $value;
                $data[$key]['index'] = $key;
            }

            // Optimize by line
            // If the table is in the high tables list, we optimize it by line
            elseif ($isHighTable && $optimizeByLine) {
                $rows = wp_umbrella_get_service('DatabaseTablesProvider')->getTableSizeDetailWithHighValue($value['table']);
                $columnData = wp_umbrella_get_service('DatabaseTablesProvider')->getColumnCharLengthCheckByTable($value['table']);
                $batch = [];

                $currentBatchBytes = 0;
                $ids = [];

                foreach ($rows as $row) {
                    if ($currentBatchBytes + $row['bytes'] > $maximumMemory) {
                        $batch[] = $ids;
                        $currentBatchBytes = 0;
                        $ids = [];
                    }

                    $currentBatchBytes += (int) $row['bytes'];
                    $ids[] = $row['id'];
                }

                $rowsWithoutHighContent = wp_umbrella_get_service('DatabaseTablesProvider')->getTableSizeDetailWithNoHighValue($value['table']);

                $currentBatchBytes = 0;
                $ids = [];

                foreach ($rowsWithoutHighContent as $row) {
                    if ($currentBatchBytes + $row['bytes'] > $maximumMemory) {
                        $currentBatchBytes = 0;
                        $ids = [];
                    }

                    $currentBatchBytes += (int) $row['bytes'];
                    $ids[] = $row['id'];
                }

                $batch[] = $ids;

                $data[$key]['batch_by_line'] = $batch;
                $data[$key]['id'] = $columnData['id'];
                $data[$key]['index'] = $key;
            }
        }

        return $data;
    }
}
