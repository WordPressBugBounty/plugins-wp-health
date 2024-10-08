<?php

namespace WPUmbrella\Models\Table;

class Table implements TableInterface {

	protected $name;

	protected $structure;

	protected $alias;

	protected $version;

    public function __construct($name, TableStructureInterface $structure, $options = []){
        $this->name = $name;
        $this->structure = $structure;
        $this->alias = isset($options['alias']) ? $options['alias'] : substr($name, 9,3);
        $this->version = isset($options['version']) ? (int) $options['version'] : 1;
    }

    /**
     * @return string
     */
	public function getName(){
        return $this->name;
    }

    /**
     * @return string
     */
	public function getAlias(){
        return $this->alias;
    }

    public function getColumns(){
        return $this->structure->getColumns();
    }

    public function getVersion(){
        return $this->version;
    }

    public function getColumnByName($name){
        foreach ($this->getColumns() as $key => $value) {
            if($value->getName() === $name){
                return $value;
            }
        }
    }

}
