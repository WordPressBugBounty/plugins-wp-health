<?php

namespace WPUmbrella\Core\Table;

defined( 'ABSPATH' ) || exit;

use WPUmbrella\Models\Table\TableInterface;
use WPUmbrella\Models\Table\TableColumnInterface;

class QueryCreateTable {


    public function constructColumn(TableColumnInterface $column){
        $line = sprintf("%s %s", $column->getName(), $column->getType());
        if($column->getPrimaryKey()){
            $line .= ' NOT NULL AUTO_INCREMENT';
        }
		else if(!empty($column->getDefaultValue())){
			$line .= ' DEFAULT ' . $column->getDefaultValue();
		}
        else{
            $line .= ' DEFAULT NULL';
        }

        return $line;
    }

    public function getPrimaryKey($columns){
        $value = '';
        foreach ($columns as $key => $column) {
            if(!$column->getPrimaryKey()){
                continue;
            }

            if(empty($value)){
                $value .= 'PRIMARY KEY (';
            }
            else{
                $value .= ', ';
            }

            $value .= $column->getName();

        }
        if(!empty($value)){
            $value .= ')';
        }

        return $value;
    }

    public function create(TableInterface $table){

        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $indexes = [];

        $data = [];
        $columns = $table->getColumns();
        foreach ($columns as $key => $column) {
            $data[$key] = $this->constructColumn($column);

            $columnIndexable = $column->getIndex();
            if(!$columnIndexable){
                continue;
            }

            $indexes[] = "CREATE INDEX idx_{$column->getName()} ON {$wpdb->prefix}{$table->getName()} ({$column->getName()})";
        }

        $primaryKey = $this->getPrimaryKey($columns);

        if(!empty($primaryKey)){
            $data[] = $primaryKey;
        }

        $tableName = $wpdb->prefix . $table->getName();

        $sql   = array();
        $sql[] = "CREATE TABLE {$tableName} (";
        $sql[] = implode(", ", $data);
        $sql[] = ") {$charset};";

        $sql = implode("\n", $sql);

        if(!function_exists("dbDelta")){
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        try {
            $create = maybe_create_table( $tableName, $sql );

			if($create === false){
				$sql   = array();
				$sql[] = "CREATE TABLE {$tableName} (";
				$sql[] = implode(", ", $data);
				$sql[] = ") DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;";

				$sql = implode("\n", $sql);

				maybe_create_table( $tableName, $sql );
			}
        } catch (\Exception $e) {
            return false;
        }

        try {
            foreach($indexes as $index){
                $wpdb->query($index);
            }
        } catch (\Exception $e) {
            return false;
        }

    }

}
