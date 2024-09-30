<?php

if(!class_exists('UmbrellaSqlInstruction', false)):
    class UmbrellaSqlInstruction
    {
        public static function createSelectQuery($tableName, array $columns)
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
            $select .= " FROM `$tableName`;";

            return $select;
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

        public static function dumpTable(UmbrellaConnectionInterface $connection, $table, UmbrellaFileHandle $fileHandle)
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
            $fetchAllQuery = self::createSelectQuery($tableName, $columns);
            $haltCompiler = '#<?php die(); ?>';
            $dumper = get_class($connection);
            $phpVersion = phpversion();
            $header = <<<SQL
    $haltCompiler
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
                $flushSize = 8 << 20;
                $buf = '';
                $fetchAll = $connection->query($fetchAllQuery, [], true);
                while ($row = $fetchAll->fetch()) {
                    $buf .= self::createInsertQuery($connection, $tableName, $columns, $row);
                    if (strlen($buf) < $flushSize) {
                        continue;
                    }
                    $fileHandle->write($buf);
                    $written += strlen($buf);
                    $buf = '';
                }
                if (strlen($buf)) {
                    $fileHandle->write($buf);
                    $written += strlen($buf);
                    unset($buf);
                }
                $fetchAll->free();
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

            return $written;
        }
    }

endif;
