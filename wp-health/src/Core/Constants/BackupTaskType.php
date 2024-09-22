<?php

namespace WPUmbrella\Core\Constants;

abstract class BackupTaskType {
	const BACKUP_FILES = 'backup_files';
	const BACKUP_DATABASE = 'backup_database';

	const BACKUP_PREPARE_BATCH_DATABASE = 'backup_prepare_batch_database';
	const BACKUP_TABLE_CHECK_BATCH = 'backup_table_check_batch';
	const BACKUP_NEED_CLEANUP = 'backup_need_cleanup';

}
