<?php

namespace WPUmbrella\Models\Table;

use WPUmbrella\Models\Table\TableColumnInterface;

class TableColumn implements TableColumnInterface {

	protected $name;

	protected $type;

	protected $primaryKey;

	protected $index;

	protected $defaultValue;

    public function __construct($name, $data = []){

        $this->name = $name;
        $this->type = isset($data['type']) ? $data['type'] : 'varchar';
        $this->primaryKey = isset($data['primaryKey']) ? (bool) $data['primaryKey'] : false;
        $this->index = isset($data['index']) ? $data['index'] : false;
		$this->defaultValue = isset($data['default']) ? $data['default'] : null;
    }

    /**
	 * @return int
	 */
	public function getType(){
		if($this->type !== "datetime"){
			return $this->type;
		}

		if($this->defaultValue !== "CURRENT_TIMESTAMP"){
			return $this->type;
		}

		global $wpdb;
		$server = $wpdb->get_var( 'SELECT VERSION()' );

		// Compatibility DB version < 5.6.5 don't support CURRENT_TIMESTAMP
		if(version_compare($server, '5.6.5', '<')){
			return 'timestamp';
		}

        return $this->type;
    }

	/**
	 * @return string
	 */
	public function getName(){
        return $this->name;
    }

    /**
     * @return bool
     */
	public function getPrimaryKey(){
        return $this->primaryKey;
    }


	public function getDefaultValue(){
		return $this->defaultValue;
	}

    public function getIndex(){
        return $this->index;
    }


}
