<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;
class CleanRedirects extends AbstractController
{
    public function executeGet($params)
    {
        return $this->clean($params);
    }

    public function executePost($params)
    {
        return $this->clean($params);
    }

    protected function clean($params)
    {
        global $wpdb;
        $tableName = RedirectTableManager::getTableName();

        $wpdb->query("TRUNCATE TABLE {$tableName}");
        return $this->returnResponse(['success' => true]);
    }
}
