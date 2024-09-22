<?php

namespace WPUmbrella\Core\Constants;

abstract class BackupTaskStatus {
	const SUCCESS = 'success';
	const IN_PROGRESS = 'in_progress';
	const ERROR = 'error';
	const STOPPED = 'stopped';
}
