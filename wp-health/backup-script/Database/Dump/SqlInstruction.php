<?php

if (!class_exists('UmbrellaSqlInstruction', false)):
    class UmbrellaSqlInstruction
    {
        /**
         * Tables with high row density (heavy data per row)
         * These tables need size checking before SELECT to avoid max_allowed_packet issues
         */
        const HIGH_DENSITY_TABLES = [
            'postmeta',
            'posts',
        ];

        /**
         * Factor to estimate byte size from CHAR_LENGTH
         * Same as BackupData::FACTOR_CHAR_LENGTH
         */
        const FACTOR_CHAR_LENGTH = 2.3;

        /**
         * Default maximum memory for a single SELECT batch (16 MB - safe for most MySQL configs)
         */
        const DEFAULT_MAX_BATCH_MEMORY = 16 * 1024 * 1024;

        /**
         * Minimum batch size to avoid infinite loops
         */
        const MIN_BATCH_SIZE = 100;

        /**
         * Check if a table is high-density based on its name
         *
         * @param string $tableName Full table name (e.g., wp_postmeta)
         * @return bool
         */
        public static function isHighDensityTable($tableName)
        {
            foreach (self::HIGH_DENSITY_TABLES as $suffix) {
                if (substr($tableName, -strlen($suffix)) === $suffix) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Estimate the size of rows for a given LIMIT/OFFSET
         * Similar to DatabaseTables::getSizeOfLines but works with UmbrellaConnectionInterface
         *
         * @param UmbrellaConnectionInterface $connection
         * @param string $tableName
         * @param array $columns Column objects with 'name' property
         * @param int $limit
         * @param int $offset
         * @return int Estimated size in bytes
         */
        public static function estimateBatchSize(UmbrellaConnectionInterface $connection, $tableName, array $columns, $limit, $offset)
        {
            $columnNames = array_map(function ($col) {
                return "`{$col->name}`";
            }, $columns);

            $concatColumns = implode(', ', $columnNames);
            $factor = self::FACTOR_CHAR_LENGTH;

            $query = "SELECT SUM(size) as size FROM (
                SELECT CHAR_LENGTH(CONCAT({$concatColumns})) * {$factor} as size
                FROM `{$tableName}`
                LIMIT {$limit} OFFSET {$offset}
            ) as TableSize";

            try {
                $result = $connection->query($query)->fetch();
                return (int) ($result['size'] ?? 0);
            } catch (Exception $e) {
                // If query fails, return 0 and let the normal flow continue
                return 0;
            }
        }

        /**
         * Calculate optimal batch size for high-density tables based on estimated row weight
         *
         * @param UmbrellaConnectionInterface $connection
         * @param string $tableName
         * @param array $columns
         * @param int $requestedBatchSize Original batch size requested
         * @param int $offset Current offset
         * @param int $maxMemory Maximum memory allowed for batch
         * @return int Adjusted batch size
         */
        public static function getOptimalBatchSize(
            UmbrellaConnectionInterface $connection,
            $tableName,
            array $columns,
            $requestedBatchSize,
            $offset,
            $maxMemory = self::DEFAULT_MAX_BATCH_MEMORY
        ) {
            // Estimate size for requested batch
            $estimatedSize = self::estimateBatchSize($connection, $tableName, $columns, $requestedBatchSize, $offset);

            if ($estimatedSize === 0 || $estimatedSize <= $maxMemory) {
                return $requestedBatchSize;
            }

            // Calculate optimal batch size based on ratio
            $ratio = $maxMemory / $estimatedSize;
            $optimalBatchSize = (int) floor($requestedBatchSize * $ratio * 0.9); // 10% safety margin

            return max($optimalBatchSize, self::MIN_BATCH_SIZE);
        }

        public static function createSelectQuery($tableName, array $columns, $batchSize = 0, $offset = 0)
        {
            $select = 'SELECT ';
            foreach ($columns as $i => $column) {
                if ($i > 0) {
                    $select .= ', ';
                }
                switch ($column->type) {
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'blob':
                    case 'longblob':
                    case 'binary':
                    case 'varbinary':
                        $select .= "HEX(`$column->name`)";
                        break;
                    default:
                        $select .= "`$column->name`";
                        break;
                }
            }
            $select .= " FROM `$tableName`";

            if ($batchSize === 0) {
                return $select . ';';
            }

            return $select . " LIMIT $batchSize OFFSET $offset;";
        }

        /**
         * Build only the VALUES tuple for a row, to be used in multi-row INSERT batching.
         */
        public static function createValuesTuple(UmbrellaConnectionInterface $connection, array $columns, array $row)
        {
            $tuple = '(';
            $i = 0;
            foreach ($row as $value) {
                $column = $columns[$i];
                if ($i > 0) {
                    $tuple .= ',';
                }
                $i++;
                if ($value === null) {
                    $tuple .= 'null';
                    continue;
                }
                switch ($column->type) {
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'int':
                    case 'bigint':
                    case 'decimal':
                    case 'float':
                    case 'double':
                        $tuple .= $value;
                        break;
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'blob':
                    case 'longblob':
                    case 'binary':
                    case 'varbinary':
                        if (strlen($value) === 0) {
                            $tuple .= "''";
                        } else {
                            $tuple .= "0x$value";
                        }
                        break;
                    case 'bit':
                        $tuple .= $value ? "b'1'" : "b'0'";
                        break;
                    default:
                        $tuple .= $connection->escape($value);
                        break;
                }
            }
            $tuple .= ')';

            return $tuple;
        }

        public static function dumpTupleTable(UmbrellaConnectionInterface $connection, $table, $fileHandle, UmbrellaWebSocket $socket, $batchSize = 0, $intervalBetweenBatch = 0)
        {
            $tableName = $table['name'];
            $noData = $table['noData'];
            $columns = $table['columns'];
            $written = 0;
            $result = $connection->query("SHOW CREATE TABLE `$tableName`")->fetch();
            $createTable = $result['Create Table'];
            if (empty($createTable)) {
                throw new UmbrellaException(sprintf('SHOW CREATE TABLE did not return expected result for table %s', $tableName), 'no_create_table');
            }

            $time = date('c');
            $fetchAllQuery = self::createSelectQuery($tableName, $columns, $batchSize, 0);
            $haltCompiler = '';
            $dumper = get_class($connection);
            $phpVersion = phpversion();
            $header = <<<SQL
    $haltCompiler
    -- <?php die(); ?>
    -- Umbrella backup format
    -- Generated at: $time by $dumper; PHP v$phpVersion
    -- Selected via: $fetchAllQuery

    /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
    /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
    /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
    /*!40101 SET NAMES utf8 */;
    /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
    /*!40103 SET TIME_ZONE='+00:00' */;
    /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
    /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
    /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
    /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

    DROP TABLE IF EXISTS `$tableName`;

    /*!40101 SET @saved_cs_client     = @@character_set_client */;
    /*!40101 SET character_set_client = utf8 */;

    $createTable;

    /*!40101 SET character_set_client = @saved_cs_client */;

    SQL;
            if (!$noData) {
                $header .= <<<SQL
    LOCK TABLES `$tableName` WRITE;
    /*!40000 ALTER TABLE `$tableName` DISABLE KEYS */;

    SQL;
            }
            $fileHandle->write($header);
            $written += strlen($header);

            if (!$noData) {
                // Smaller flush size to reduce memory spikes on very large tables
                $flushSize = 2 << 20; // 2 MiB
                $valuesPerInsert = 300; // batch rows per INSERT

                $buf = '';
                $currentValues = [];

                if ($batchSize === 0) {
                    // Fetch all rows at once
                    $fetchAll = $connection->query($fetchAllQuery, [], true);
                    while ($row = $fetchAll->fetch()) {
                        $currentValues[] = self::createValuesTuple($connection, $columns, $row);

                        if (count($currentValues) >= $valuesPerInsert) {
                            $insert = "INSERT INTO `$tableName` VALUES " . implode(',', $currentValues) . ";\n";
                            $buf .= $insert;
                            $currentValues = [];
                        }

                        if (strlen($buf) >= $flushSize) {
                            $fileHandle->write($buf);
                            $written += strlen($buf);
                            $buf = '';
                        }
                    }
                    $fetchAll->free();
                } else {
                    // Fetch rows in batches
                    $offset = 0;
                    $hasMoreRows = true;

                    while ($hasMoreRows) {
                        // For high-density tables, check and adjust batch size based on estimated weight
                        // $currentBatchSize = $batchSize;

                        $currentBatchSize = self::getOptimalBatchSize(
                            $connection,
                            $tableName,
                            $columns,
                            $batchSize,
                            $offset
                        );

                        if ($currentBatchSize !== $batchSize) {
                            $socket->sendLog(sprintf(
                                'Adjusted batch size for %s: %d -> %d (offset: %d)',
                                $tableName,
                                $batchSize,
                                $currentBatchSize,
                                $offset
                            ));
                        } else {
                            $socket->sendLog(sprintf(
                                'No adjustment needed for %s: %d (offset: %d)',
                                $tableName,
                                $batchSize,
                                $offset
                            ));
                        }

                        $batchQuery = self::createSelectQuery($tableName, $columns, $currentBatchSize, $offset);

                        $fetchAll = $connection->query($batchQuery, [], true);
                        $rowsInBatch = 0;

                        while ($row = $fetchAll->fetch()) {
                            $rowsInBatch++;
                            $currentValues[] = self::createValuesTuple($connection, $columns, $row);

                            if (count($currentValues) >= $valuesPerInsert) {
                                $insert = "INSERT INTO `$tableName` VALUES " . implode(',', $currentValues) . ";\n";
                                $buf .= $insert;
                                $currentValues = [];
                            }

                            if (strlen($buf) >= $flushSize) {
                                $fileHandle->write($buf);
                                $written += strlen($buf);
                                $buf = '';
                            }
                        }

                        $fetchAll->free();

                        // If we got fewer rows than currentBatchSize, we've reached the end
                        if ($rowsInBatch < $currentBatchSize || $rowsInBatch === 0) {
                            $hasMoreRows = false;
                        } else {
                            $offset += $rowsInBatch;
                        }

                        if ($intervalBetweenBatch <= 0) {
                            continue;
                        }

                        $TEN_MS = 10000000;
                        $socket->sendLog('Sleeping ' . $TEN_MS . ' nanoseconds between batches for table ' . $tableName);
                        time_nanosleep(0, $TEN_MS);
                    }
                }

                if (!empty($currentValues)) {
                    $insert = "INSERT INTO `$tableName` VALUES " . implode(',', $currentValues) . ";\n";
                    $buf .= $insert;
                    $currentValues = [];
                }

                if (strlen($buf)) {
                    $fileHandle->write($buf);
                    $written += strlen($buf);
                    unset($buf);
                }
                $socket->sendLog('Finished creating values tuple for rows');
            }

            $footer = <<<SQL

    /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
    /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
    /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
    /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
    /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
    /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
    /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
    /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

    SQL;
            if (!$noData) {
                $footer = <<<SQL

    /*!40000 ALTER TABLE `$tableName` ENABLE KEYS */;
    UNLOCK TABLES;
    SQL
                    . $footer;
            }
            $fileHandle->write($footer);
            $written += strlen($footer);

            $socket->sendLog('Finished dumping tuple table ' . $tableName);
            $socket->sendLog('Size: ' . $written);
            return $written;
        }

        public static function createInsertQuery(UmbrellaConnectionInterface $connection, $tableName, array $columns, array $row)
        {
            $insert = "INSERT INTO `$tableName` VALUES (";
            $i = 0;
            foreach ($row as $value) {
                $column = $columns[$i];
                if ($i > 0) {
                    $insert .= ',';
                }
                $i++;
                if ($value === null) {
                    $insert .= 'null';
                    continue;
                }
                switch ($column->type) {
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'int':
                    case 'bigint':
                    case 'decimal':
                    case 'float':
                    case 'double':
                        $insert .= $value;
                        break;
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'blob':
                    case 'longblob':
                    case 'binary':
                    case 'varbinary':
                        if (strlen($value) === 0) {
                            $insert .= "''";
                        } else {
                            $insert .= "0x$value";
                        }
                        break;
                    case 'bit':
                        $insert .= $value ? "b'1'" : "b'0'";
                        break;
                    default:
                        $insert .= $connection->escape($value);
                        break;
                }
            }
            $insert .= ");\n";

            return $insert;
        }
    }
endif;
