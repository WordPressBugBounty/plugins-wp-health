<?php
namespace WPUmbrella\Services\Backup;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupProcessedData;

class BackupBatchData
{
    protected $data = null;

    public function getOptionNameByType($type)
    {
        switch ($type) {
            case 'file':
                return 'wp_umbrella_backup_profile_data';
            case 'database':
                return 'wp_umbrella_backup_profile_database_data';
        }
    }

    public function getData($type = 'file')
    {
        if ($type === 'file') {
            $data = get_option('wp_umbrella_backup_profile_data');
        } else {
            $data = get_option('wp_umbrella_backup_profile_database_data');
        }

        if (!$data) {
            return null;
        }

        if ($this->data !== null) {
            return $this->data;
        }

        $obj = new BackupProcessedData();
        $obj->initData($data);
        $this->data = $obj;
        return $this->data;
    }

    public function setDataOption($data, $type = 'all')
    {
        switch ($type) {
            case 'file':
                update_option('wp_umbrella_backup_profile_data', $data, false);
                break;
            case 'database':
                update_option('wp_umbrella_backup_profile_database_data', $data, false);
                break;
            case 'all':
                update_option('wp_umbrella_backup_profile_data', $data, false);
                update_option('wp_umbrella_backup_profile_database_data', $data, false);
                break;
        }
    }

    public function deleteDataOption()
    {
        wp_umbrella_get_service('BackupRunner')->unscheduledBatch();
        delete_option('wp_umbrella_backup_profile_data');
        delete_option('wp_umbrella_backup_profile_database_data');
    }
}
