<?php

namespace WPUmbrella\Models\Table;


interface TableInterface {


    /**
     * @return string
     */
	public function getName();

    public function getColumns();

}
