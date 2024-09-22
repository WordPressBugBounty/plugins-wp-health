<?php

namespace WPUmbrella\Models\Table;


interface TableColumnInterface {


    /**
	 * @return int
	 */
	public function getType();

	/**
	 * @return string
	 */
	public function getName();

    /**
     * @return bool
     */
	public function getPrimaryKey();

}
