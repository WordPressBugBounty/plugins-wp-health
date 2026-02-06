<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Helper\Host;
use WPUmbrella\Services\DirectoryFunctions;

class PrepareBackupData extends AbstractController
{
    public function executeGet($params)
    {
        global $wpdb;

        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();

        // Clean up old backup files
        $files = [
            $source . 'cloner.php',
            $source . 'cloner_error_log'
        ];

        // Find any dictionary files matching pattern
        $dictionaryFiles = glob($source . '*-dictionary.php');
        $directoryDictionaryFiles = glob($source . '*-directory-dictionary.php');

        $files = array_merge($files, $dictionaryFiles, $directoryDictionaryFiles);

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            @unlink($file);
        }

        // Clean up database directories
        $directories = [
            $source . 'umb_database',
            $source . 'umb_checksum',
            $source . 'wp-content' . DIRECTORY_SEPARATOR . 'umb_database',
        ];

        foreach ($directories as $directory) {
            DirectoryFunctions::destroyDir($directory);
        }

        return $this->returnResponse([
            'prefix' => $wpdb->prefix,
            'baseDirectory' => wp_umbrella_get_service('BackupFinderConfiguration')->getDefaultSource(),
            'database' => [
                'db_host' => wp_umbrella_get_service('WordPressContext')->getDbHost(),
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
