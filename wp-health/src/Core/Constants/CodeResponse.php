<?php

namespace WPUmbrella\Core\Constants;

abstract class CodeResponse {
	const SUCCESS = 'success';
	const ERROR = 'error';


    const BACKUP_ERROR = 'backup_error';
    const BACKUP_ALREADY_PROCESS = 'backup_already_process';
	const BACKUP_NEXT_PART_FILES = "backup_next_part_files";
	const BACKUP_NEXT_PART_DATABASE = "backup_next_part_database";
	const BACKUP_TABLE_NEED_BATCH = 'backup_table_need_batch';
	const BACKUP_TABLE_CHECK_BATCH = 'backup_table_check_batch';
	const BACKUP_DATABASE_FINISH = 'backup_database_finish';
	const BACKUP_NEED_CLEANUP = 'backup_need_cleanup';
	const BACKUP_FILES_FINISH = 'backup_files_finish';
	const BACKUP_FINISH =  'backup_finish';
}
