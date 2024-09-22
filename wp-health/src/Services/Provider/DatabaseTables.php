<?php
namespace WPUmbrella\Services\Provider;

use WPUmbrella\Core\Constants\BackupData;

class DatabaseTables
{
    const NAME_SERVICE = 'DatabaseTablesProvider';

    public function getSizeOfLines($tableName, $columns, $options = [])
    {
        $offset = $options['offset'] ?? 0;
        $limit = $options['limit'] ?? 1000;

        global $wpdb;

        $columns = '`' . implode('`, `', $columns) . '`';

        $query = sprintf("
			SELECT SUM(size) as size
			FROM (
				SELECT CHAR_LENGTH(CONCAT({$columns}))*%s  as size
				FROM %s
				LIMIT %s OFFSET %s
			) as TableSize
		", BackupData::FACTOR_CHAR_LENGTH, esc_sql($tableName), $limit, $offset);

        $result = $wpdb->get_results(
            $query,
            ARRAY_A
        );

        if (empty($result) || !isset($result[0])) {
            return 0;
        }

        return $result[0]['size'];
    }

    /**
     * @param string $tableName
     */
    public function getTableStatus($tableName)
    {
        global $wpdb;

        $db = wp_umbrella_get_service('WordPressContext')->getConstant('DB_NAME');
        $result = $wpdb->get_results(
            sprintf('SHOW TABLE STATUS FROM `%s` WHERE Name = "%s"', esc_sql($db), esc_sql($tableName)),
            ARRAY_A
        );

        return $result[0];
    }

    public function getTableColumns($tableName)
    {
        global $wpdb;

        $result = $wpdb->get_results(
            sprintf('SHOW COLUMNS FROM `%s`', esc_sql($tableName)),
            ARRAY_A
        );

        return $result;
    }

    public function getTablesChecksum(array $params = [])
    {
        global $wpdb;

        if (!isset($params['tables'])) {
            return [];
        }

        $tables = array_map(function ($tableName) {
            return sprintf('`%s`', esc_sql($tableName));
        }, $params['tables']);

        $query = implode(',', $tables);

        $results = $wpdb->get_results('CHECKSUM TABLE ' . $query, ARRAY_A);
        $checksum = [];

        foreach ($results as $row) {
            $tableName = str_replace(sprintf('%s.', $wpdb->dbname), '', $row['Table']);
            $checksum[$tableName] = $row['Checksum'];
        }

        return $checksum;
    }

    public function getTables()
    {
        try {
            global $wpdb;
            $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
            return array_reduce($tables, function ($current, $item) {
                array_push($current, $item[0]);
                return $current;
            }, []);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getTablesWithSize()
    {
        global $wpdb;

        $db = wp_umbrella_get_service('WordPressContext')->getConstant('DB_NAME');
        $likePrefix = sprintf('%s%%', $wpdb->prefix);

        $tables = $wpdb->get_results(
            $wpdb->prepare('SELECT table_name AS "name", data_length as size FROM information_schema.TABLES WHERE table_schema = %s AND table_name LIKE %s', $db, $likePrefix),
            ARRAY_A
        );

        $checksum = $this->getTablesChecksum([
            'tables' => array_map(function ($table) {
                return $table['name'];
            }, $tables)
        ]);

        foreach ($tables as $key => $table) {
            $columns = $this->getTableColumns($table['name']);
            $status = $this->getTableStatus($table['name']);
            $tables[$key]['columns'] = array_map(function ($column) {
                return $column['Field'];
            }, $columns);

            $tables[$key]['rows'] = isset($status['Rows']) ? (int) $status['Rows'] : 0;

            $tables[$key]['size'] = (int) $table['size'];
            $tables[$key]['checksum'] = isset($checksum[$table['name']]) ? $checksum[$table['name']] : null;
        }

        return $tables;
    }

    /**
     * @return array
     * [
     * 		[
     * 			'table' => 'wp_posts',
     * 			'size_mb' => 10
     * 		]
     * 		...
     * 	]
     */
    public function getTablesSize()
    {
        try {
            global $wpdb;
            $tables = $wpdb->get_results("
				SELECT
					TABLE_NAME AS 'table',
					ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) AS 'size_mb'
				FROM
					information_schema.TABLES
				WHERE
					TABLE_SCHEMA = '{$wpdb->dbname}'
				AND
					TABLE_NAME LIKE '{$wpdb->prefix}%'
				ORDER BY
					(DATA_LENGTH + INDEX_LENGTH)
				DESC
			", ARRAY_A);

            return array_map(function ($item) {
                return [
                    'table' => $item['table'],
                    'size_mb' => (int) $item['size_mb'],
                ];
            }, $tables);
        } catch (\Exception $th) {
            return [];
        }
    }

    public function getColumnCharLengthCheckByTable($table)
    {
        global $wpdb;

        switch ($table) {
            case "{$wpdb->prefix}postmeta":
                return [
                    'column' => 'meta_value',
                    'id' => 'meta_id'
                ];
            case "{$wpdb->prefix}posts":
                return [
                    'column' => 'post_content',
                    'id' => 'ID'
                ];
            default:
                return apply_filters('wp_umbrella_get_column_char_length', [
                    'column' => '',
                    'id' => ''
                ], $table);
        }
    }

    public function getTableSizeDetailWithHighValue($table)
    {
        try {
            global $wpdb;

            $data = $this->getColumnCharLengthCheckByTable($table);
            $column = $data['column'];
            $id = $data['id'];

            // Multiple by 3 for prevent unicode
            $tables = $wpdb->get_results("
				SELECT (CHAR_LENGTH({$column})*3) as bytes, `{$id}` as id
				FROM {$table}
				HAVING bytes IS NOT NULL
			", ARRAY_A);

            return $tables;
        } catch (\Exception $th) {
            return [];
        }
    }

    public function getTableSizeDetailWithNoHighValue($table)
    {
        try {
            global $wpdb;

            $data = $this->getColumnCharLengthCheckByTable($table);
            $column = $data['column'];
            $id = $data['id'];

            // Multiple by 3 for prevent unicode
            $tables = $wpdb->get_results("
				SELECT (CHAR_LENGTH({$column})*3) as bytes, `{$id}` as id
				FROM {$table}
				HAVING bytes IS NULL
			", ARRAY_A);

            return $tables;
        } catch (\Exception $th) {
            return [];
        }
    }

    public function getCountRows($table)
    {
        global $wpdb;
        $result = $wpdb->get_row("SELECT COUNT(*) as 'count' FROM {$table}", ARRAY_A);
        return (int) $result['count'];
    }
}
