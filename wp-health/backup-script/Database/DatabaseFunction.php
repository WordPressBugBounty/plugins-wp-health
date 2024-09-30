<?php

if(!class_exists('UmbrellaDatabaseFunction', false)):
    abstract class UmbrellaDatabaseFunction
    {
        protected static $connection;

        public static function getConnection($params)
        {
            if(null !== self::$connection) {
                return self::$connection;
            }

            if (extension_loaded('mysqli')) {
                self::$connection = new UmbrellaMySQLiConnection($params);
            } elseif (extension_loaded('pdo_mysql')) {
                self::$connection = new UmbrellaPDOConnection($params);
            } elseif (extension_loaded('mysql')) {
                self::$connection = new UmbrellaMySQLConnection($params);
            } else {
                throw new UmbrellaException('No drivers available for php mysql connection.', 'no_db_drivers');
            }

            return self::$connection;
        }

        public static function getTableSchemaOnly($tableName, $prefix = 'wp_')
        {
            $ignored = array_map(function ($table) use ($prefix) {
                return $prefix . $table;
            }, [
                'wysija_user_history',
                '_wsd_plugin_alerts',
                '_wsd_plugin_live_traffic',
                'adrotate_tracker',
                'aiowps_events',
                'ak_404_log',
                'bad_behavior',
                'cn_track_post',
                'nginxchampuru',
                'popover_ip_cache',
                'redirection_404',
                'spynot_systems_log',
                'statify',
                'statistics_useronline',
                'tcb_api_error_log',
                'useronline',
                'wbz404_logs',
                'wfHits',
                'wfLeechers',
                'who_is_online',
                'simple_history',
                'simple_history_contexts',
                'wfHoover',
                'et_bloom_stats',
                'itsec_log',
                'itsec_logs',
                'itsec_temp',
                'cpd_counter',
                'session',
                'wpaas_activity_log',
                'umbrella_log',
                'woocommerce_log',
                'fsmpt_email_logs',
                'email_log',
                'amelia_notifications_log',
                'bookly_log',
                'actionscheduler_actions',
                'actionscheduler_logs'
            ]);

            if(in_array($tableName, $ignored)) {
                return true;
            }

            return false;
        }

        public static function getDatabaseInformation(UmbrellaConnectionInterface $connection)
        {
            $info = [
                'collation' => [],
                'charset' => [],
            ];

            $list = $connection->query('SHOW COLLATION')->fetchAll();
            foreach ($list as $row) {
                $info['collation'][$row['Collation']] = true;
                $info['charset'][$row['Charset']] = true;
            }
            return $info;
        }

        public static function getListTables(UmbrellaConnectionInterface $connection, UmbrellaContext $context)
        {
            $tableNames = $context->getTables();

            if(empty($tableNames)) {
                return [];
            }

            $result = [];

            $tables = $connection->query(
                'SELECT `table_name` AS `name`, `data_length` AS `dataSize`
                            FROM information_schema.TABLES
                            WHERE table_schema = :db_name AND table_type = :table_type AND engine IS NOT NULL',
                [
                    'db_name' => $connection->getConfiguration()->name,
                    'table_type' => 'BASE TABLE', // as opposed to VIEW
                ]
            )->fetchAll();

            foreach ($tables as $table) {
                if (!in_array($table['name'], $tableNames, true)) {
                    continue;
                }

                $result[] = [
                    'name' => $table['name'],
                    'type' => UmbrellaTableType::REGULAR,
                    'dataSize' => (int)$table['dataSize'],
                    'noData' => self::getTableSchemaOnly($table['name'], $context->getDatabasePrefix()),
                ];
            }

            return $result;
        }

        public static function getTableColumns(UmbrellaConnectionInterface $connection, $table)
        {
            $columnList = $connection->query("SHOW COLUMNS IN `$table`")->fetchAll();

            $columns = [];
            foreach ($columnList as $columnData) {
                $column = new UmbrellaDatabaseColumn();
                $column->name = $columnData['Field'];
                $type = strtolower($columnData['Type']);
                if (($openParen = strpos($type, '(')) !== false) {
                    // Transform "int(11)" to "int", etc.
                    $type = substr($type, 0, $openParen);
                }
                $column->type = $type;
                $columns[] = $column;

                if ($connection instanceof UmbrellaPDOConnection && strpos($column->name, '?') !== false) {
                    $connection->setAttEmulatePrepares(false);
                }
            }

            return $columns;
        }

        public static function getDatabaseCharset(UmbrellaConnectionInterface $connection)
        {
            $info = self::getDatabaseInformation($connection);
            $try = 'utf8mb4';
            foreach ($info['charset'] as $charset => $true) {
                if (strpos($charset, $try) === false) {
                    continue;
                }
                return $try;
            }
            return 'utf8';
        }

        public static function bindQueryParams(UmbrellaConnectionInterface $connection, $query, array $params)
        {
            if (count($params) === 0) {
                return $query;
            }
            $replacements = [];
            foreach ($params as $name => $value) {
                $replacements[":$name"] = $connection->escape($value);
            }
            return strtr($query, $replacements);
        }
    }

endif;
