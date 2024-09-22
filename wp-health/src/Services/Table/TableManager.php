<?php

namespace WPUmbrella\Services\Table;

use WPUmbrella\Models\Table\TableInterface;
use WPUmbrella\Core\Table\QueryCreateTable;
use WPUmbrella\Core\Table\QueryExistTable;

class TableManager {

	/**
	 * @var QueryCreateTable
	 */
	protected $queryCreateTable;

	/**
	 * @var QueryExistTable
	 */
	protected $queryExistTable;

    public function __construct(){
        $this->queryCreateTable = new QueryCreateTable();
        $this->queryExistTable = new QueryExistTable();
    }

    public function exist(TableInterface $table){
        return $this->queryExistTable->exist($table);
    }

    public function create(TableInterface $table){
        if($this->exist($table)){
            return;
        }

        $this->queryCreateTable->create($table);
    }

    public function createTablesIfNeeded($tables){
        foreach ($tables as $key => $table) {
            $this->create($table);
        }
    }

}
