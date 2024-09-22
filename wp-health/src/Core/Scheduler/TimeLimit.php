<?php

namespace WPUmbrella\Core\Scheduler;

use WPUmbrella\Models\Backup\BackupTask;

use function defined;
use function function_exists;
use function getrusage;
use function ini_get;
use function microtime;
use function set_time_limit;
use function strpos;
use function apply_filters;
use function wc_set_time_limit;
use function max_execution_time;

trait TimeLimit
{
	public function raiseTimeLimit(int $limit = 0)
	{
		if (function_exists('wc_set_time_limit')) {
			wc_set_time_limit($limit);
		} elseif (
			function_exists('set_time_limit')
			&& false === strpos(ini_get('disable_functions'), 'set_time_limit')
			&& ! ini_get('safe_mode')
		) {
			@set_time_limit($limit);
		}
	}

	public function getTimeLimit(): int
	{

		if (function_exists('max_execution_time')) {
			return max_execution_time();
		}

		return 300;
	}


	public function getExecutionTime(BackupTask $task)
	{
		$dateStart = $task->getDateStart();

		$execution_time = microtime( true ) - (is_null($dateStart) ? 0 : $dateStart->getTimestamp());

		// Get the CPU time if the hosting environment uses it rather than wall-clock time to calculate a process's execution time.
		if (function_exists( 'getrusage' ) && apply_filters( 'wp_umbrella_use_cpu_execution_time', defined( 'PANTHEON_ENVIRONMENT' ) ) ) {
			$resource_usages = getrusage();

			if ( isset( $resource_usages['ru_stime.tv_usec'], $resource_usages['ru_stime.tv_usec'] ) ) {
				$execution_time = $resource_usages['ru_stime.tv_sec'] + ( $resource_usages['ru_stime.tv_usec'] / 1000000 );
			}
		}

		return $execution_time;
	}

	public function timeExceeded(BackupTask $task): bool
	{
		if(is_null($task->getDateStart())){
			return false;
		}

		return $this->getExecutionTime($task) >= $this->getTimeLimit();
	}
}
