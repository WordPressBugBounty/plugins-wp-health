<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;

class DatabaseTables extends AbstractController
{
    public function executeGet($params)
    {
        $data = wp_umbrella_get_service('DatabaseTablesProvider')->getTablesWithSize();

        return $this->returnResponse($data);
    }
}
