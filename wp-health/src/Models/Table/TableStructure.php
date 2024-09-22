<?php

namespace WPUmbrella\Models\Table;

use WPUmbrella\Models\Table\TableStructureInterface;

class TableStructure implements TableStructureInterface{

	protected $columns;

    public function __construct($columns){
        $this->columns = $columns;
    }

    /**
     * @return array
     */
	public function getColumns(){
        return $this->columns;
    }


}
