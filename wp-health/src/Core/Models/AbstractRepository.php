<?php
namespace WPUmbrella\Core\Models;

use WPUmbrella\Models\Table\Table;

abstract class AbstractRepository
{
	/**
	 * @var Table
	 */

	protected $table;

    protected function getInsertInstruction(array $args): string
    {
        global $wpdb;

		$authorizedValues = $this->getAuthorizedInsertValues();
		$columns = $this->table->getColumns();

		$data = [];
		foreach($columns as $column){
			$name = $column->getName();

			if(!in_array($name, $authorizedValues)){
				continue;
			}

			if(!isset($args[$name])){
				continue;
			}

			$data[] = $name;
		}

        return "
            INSERT INTO {$wpdb->prefix}{$this->table->getName()}
            (
				" . implode(', ', $data) . "
            ) VALUES
        ";
    }

    protected function getUpdateInstruction(): string
    {
        global $wpdb;

        return "
            UPDATE {$wpdb->prefix}{$this->table->getName()}
        ";
    }

    protected function getUpdateValues(array $args): string
    {
        global $wpdb;

		$authorizedValues = $this->getAuthorizedUpdateValues();

		foreach($args as $key => $value){
			if(!in_array($key, $authorizedValues)){
				unset($args[$key]);
			}

			if($value instanceof \DateTime){
				$args[$key] = $value->format('Y-m-d H:i:s');
			}
		}



        return "
            SET " . $this->constructSetClause($args) . "
        ";
    }

	public function constructValuesClause(array $data): string {
		$values = "(";

		foreach ($data as $value) {
			if (is_string($value)) {
				$values .= "'" . addslashes($value) . "'";
			} elseif (is_int($value)) {
				$values .= $value;
			}
			elseif ($value instanceof \DateTime){
				$values .= "'" . $value->format('Y-m-d H:i:s') . "'";
			}
			else {
				$values .= "NULL";
			}

			$values .= ",";
		}

		$values = rtrim($values, ",") . ")";

		return $values;
	}

	public function constructSetClause(array $data): string {
		$set = "";

		foreach ($data as $key => $value) {
			$set .= "{$key}='" . addslashes($value) . "',";
		}

		$set = rtrim($set, ",");

		return $set;
	}


    /**
     * Get VALUES for INSERT INTO
     *
     * @param array $args
     * @return string
     */
    protected function getInsertValuesInstruction($args): string
    {

		$authorizedValues = $this->getAuthorizedInsertValues();

		$columns = $this->table->getColumns();

		$data = [];
		foreach($columns as $column){
			$name = $column->getName();
			if(!in_array($name, $authorizedValues)){
				continue;
			}

			if(!isset($args[$name])){
				continue;
			}

			switch($name){
				case 'jobId':
				case 'backupId':
					$data[] = (int) $args[$name];
					break;
				default:
					$data[] = $args[$name];
					break;

			}
		}

		return $this->constructValuesClause($data);
    }
}
