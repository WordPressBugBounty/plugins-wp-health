<?php
namespace WPUmbrella\Services\Backup;

class BackupScan
{
    public function getData($options = [])
    {
        return [
            'curl_exist' => function_exists('curl_init'),
			'pdo_mysql' => extension_loaded('pdo_mysql'),
			'is_writable' => wp_umbrella_get_service('BackupManageProcessCustomTable')->isWritable(),
            'class_exists_zip_archive' => class_exists('ZipArchive'),
            'memory_limit' => @ini_get('memory_limit'),
			'max_execution_time' => @ini_get('max_execution_time'),
			'paths_not_allowed' => wp_umbrella_get_service('PreventErrorOnPathNotAllowed')->getInaccessiblePaths()
        ];
    }
}
