<?php
namespace WPUmbrella\Services\DatabaseOptimization;

class Table
{
    const INNODB_MIN_DATA_FREE = 5242880;

    public function getData()
    {
        global $wpdb;

        $this->useFreshTableStatistics();

        return (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE ' . $this->getFragmentedTablesCondition()
        );
    }

    public function handle()
    {
        global $wpdb;

        $this->useFreshTableStatistics();

        $tables = $wpdb->get_col(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE ' . $this->getFragmentedTablesCondition()
        );

        $data = [
            'total_optimized' => 0,
            'total_failed' => 0,
        ];

        if (empty($tables)) {
            return $data;
        }

        foreach ($tables as $table) {
            $result = $wpdb->query('OPTIMIZE TABLE `' . str_replace('`', '``', $table) . '`');

            if (false === $result) {
                $data['total_failed']++;
                continue;
            }

            $data['total_optimized']++;
        }

        return $data;
    }

    protected function getFragmentedTablesCondition()
    {
        global $wpdb;

        return $wpdb->prepare(
            "TABLE_SCHEMA = %s
				AND TABLE_TYPE = 'BASE TABLE'
				AND ENGINE IS NOT NULL
				AND DATA_FREE > 0
				AND (
					ENGINE <> 'InnoDB'
					OR (DATA_FREE >= %d AND DATA_FREE * 10 > DATA_LENGTH + INDEX_LENGTH)
				)",
            $wpdb->dbname,
            self::INNODB_MIN_DATA_FREE
        );
    }

    protected function useFreshTableStatistics()
    {
        global $wpdb;

        $suppress = $wpdb->suppress_errors(true);
        $wpdb->query('SET SESSION information_schema_stats_expiry = 0');
        $wpdb->suppress_errors($suppress);
    }
}
