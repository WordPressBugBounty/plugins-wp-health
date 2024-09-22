<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Helper\Host;

class PrepareBackupData extends AbstractController
{
    public function executeGet($params)
    {
        global $wpdb;

        return $this->returnResponse([
            'prefix' => $wpdb->prefix,
            'baseDirectory' => wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource(),
            'database' => [
                'db_host' => DB_HOST,
                'db_name' => DB_NAME,
                'db_user' => DB_USER,
                'db_password' => DB_PASSWORD,
                'db_charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8',
                'db_ssl' => defined('DB_SSL_KEY') || defined('MYSQL_CLIENT_FLAGS') ? true : false,
            ],
            'snapshot' => wp_umbrella_get_service('WordPressDataProvider')->getSnapshot(),
            'constants' => [
                'WPE_APIKEY' => defined('WPE_APIKEY') ? WPE_APIKEY : null,
            ]
        ]);
    }
}
