<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseCollector implements CollectorInterface
{
    public function getId()
    {
        return 'database';
    }

    public function collect()
    {
        global $wpdb;

        $result = [
            'prefix' => $wpdb->prefix,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'tables' => [],
            'table_count' => 0,
            'total_size_mb' => null,
            'autoloaded_options_size_kb' => null,
        ];

        // Full table info from information_schema (can be slow/blocked on some hosts)
        try {
            $tables = $wpdb->get_results(
                'SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()',
                ARRAY_A
            );

            if (is_array($tables) && !empty($tables)) {
                $totalSize = 0;
                $tableData = [];

                foreach ($tables as $table) {
                    $dataLength = isset($table['DATA_LENGTH']) ? (int) $table['DATA_LENGTH'] : 0;
                    $indexLength = isset($table['INDEX_LENGTH']) ? (int) $table['INDEX_LENGTH'] : 0;
                    $totalSize += $dataLength + $indexLength;

                    $tableData[] = [
                        'name' => $table['TABLE_NAME'],
                        'engine' => isset($table['ENGINE']) ? $table['ENGINE'] : null,
                        'rows' => (int) $table['TABLE_ROWS'],
                        'data_size' => $dataLength,
                        'index_size' => $indexLength,
                    ];
                }

                $result['tables'] = $tableData;
                $result['table_count'] = count($tableData);
                $result['total_size_mb'] = round($totalSize / 1048576, 2);
            }
        } catch (\Exception $e) {
            // Fallback: just get table names via SHOW TABLES
            $result['tables'] = $this->getTableNamesFallback($wpdb);
            $result['table_count'] = count($result['tables']);
        }

        // If information_schema returned nothing, try fallback
        if (empty($result['tables'])) {
            $result['tables'] = $this->getTableNamesFallback($wpdb);
            $result['table_count'] = count($result['tables']);
        }

        // Autoloaded options size (can be slow on large wp_options tables)
        try {
            $autoloadedSize = $wpdb->get_var(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
            );

            if ($autoloadedSize !== null) {
                $result['autoloaded_options_size_kb'] = round((int) $autoloadedSize / 1024, 2);
            }
        } catch (\Exception $e) {
            // Leave as null — the report will reflect the missing data
        }

        return $result;
    }

    protected function getTableNamesFallback($wpdb)
    {
        try {
            $tableNames = $wpdb->get_col('SHOW TABLES');

            if (!is_array($tableNames)) {
                return [];
            }

            $tables = [];
            foreach ($tableNames as $name) {
                $tables[] = [
                    'name' => $name,
                    'engine' => null,
                    'rows' => null,
                    'data_size' => null,
                    'index_size' => null,
                ];
            }

            return $tables;
        } catch (\Exception $e) {
            return [];
        }
    }
}
