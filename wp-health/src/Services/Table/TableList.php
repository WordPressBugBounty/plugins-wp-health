<?php

namespace WPUmbrella\Services\Table;

use WPUmbrella\Models\Table\TableInterface;
use WPUmbrella\Core\Table\TableFactory;
use WPUmbrella\Models\Table\TableStructure;
use WPUmbrella\Models\Table\TableColumn;
use WPUmbrella\Models\Table\Table;
use WPUmbrella\Core\Constants\BackupTaskStatus;


class TableList {


    public function getTableLog(){


        $table = new TableStructure([
            new TableColumn('id', [
                'type' => 'bigint(20)',
                'primaryKey' => true
            ]),
            new TableColumn('code', [
                'type' => 'varchar(128)',
                'index' => true,
            ]),
            new TableColumn('message', [
                'type' => 'longtext',
            ]),
            new TableColumn('backupId', [
                'type' => 'bigint(20)',
            ]),
            new TableColumn('created_at', [
                'type' => 'datetime',
				'default' => 'CURRENT_TIMESTAMP'
            ]),
        ]);

        return new Table("umbrella_log", $table);
    }

    public function getTableBackup(){
        $table = new TableStructure([
            new TableColumn('id', [
                'type' => 'bigint(20)',
                'primaryKey' => true
            ]),
            new TableColumn('count_attachments', [
                'type' => 'int(11)',
            ]),
            new TableColumn('count_public_posts', [
                'type' => 'int(11)',
            ]),
            new TableColumn('count_plugins', [
                'type' => 'int(11)',
            ]),
            new TableColumn('wp_core_version', [
                'type' => 'varchar(20)',
            ]),
            new TableColumn('config_database', [
                'type' => 'longtext',
            ]),
            new TableColumn('config_file', [
                'type' => 'longtext',
            ]),
            new TableColumn('title', [
                'type' => 'varchar(50)',
            ]),
            new TableColumn('suffix', [
                'type' => 'varchar(50)',
            ]),
            new TableColumn('is_scheduled', [
                'type' => 'tinyint',
            ]),
			new TableColumn('backupId', [
                'type' => 'bigint(20)',
            ]),
			new TableColumn('incremental_date', [
                'type' => 'varchar(50)',
            ]),
			new TableColumn('status', [
                'type' => 'varchar(128)',
            ]),
			new TableColumn('finish_file', [
                'type' => 'tinyint',
				'default' => 0
            ]),
			new TableColumn('finish_database', [
                'type' => 'tinyint',
				'default' => 0
            ]),
        ]);

        return new Table("umbrella_backup", $table);
    }

    public function getTableTaskBackup(){


        $table = new TableStructure([
            new TableColumn('id', [
                'type' => 'bigint(20)',
                'primaryKey' => true
            ]),
			new TableColumn('date_schedule', [
                'type' => 'datetime',
				'default' => 'CURRENT_TIMESTAMP'
            ]),
			new TableColumn('date_start', [
                'type' => 'datetime',
            ]),
			new TableColumn('date_end', [
                'type' => 'datetime',
            ]),
			new TableColumn('jobId', [
                'type' => 'bigint(20)',
            ]),
			new TableColumn('type', [
                'type' => 'varchar(50)',
            ]),
			new TableColumn('status', [
                'type' => 'varchar(128)',
            ]),
			new TableColumn('backupId', [
                'type' => 'bigint(20)',
            ]),
			new TableColumn('log', [
                'type' => 'longtext',
            ]),
        ]);

        return new Table("umbrella_task_backup", $table);
    }

    public function getTables(){
        return [
            "umbrella_log" => $this->getTableLog(),
            "umbrella_backup" => $this->getTableBackup(),
            "umbrella_task_backup" => $this->getTableTaskBackup(),
        ];
    }
}
