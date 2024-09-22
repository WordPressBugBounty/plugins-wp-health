<?php

namespace WPUmbrella\Core\Scheduler;

use WPUmbrella\Services\Scheduler\Scheduler;

trait QueueRunner
{

	/**
	 * @var Scheduler
	 */
	protected $scheduler;

	public function cronHooks()
	{
		if ( ! wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time(), self::CRON_SCHEDULE, self::CRON_HOOK);
		}

		add_action(self::CRON_HOOK, array($this, 'runQueueRunner'));
	}

	public function runQueueRunner()
	{
		if ( ! $this->scheduler->isAllowed()) {
			return;
		}

		$this->scheduler->execute();
		$this->clearCaches();
	}

	protected function clearCaches()
	{
		/*
		 * Calling wp_cache_flush_runtime() lets us clear the runtime cache without invalidating the external object
		 * cache, so we will always prefer this when it is available (but it was only introduced in WordPress 6.0).
		 */
		if (function_exists('wp_cache_flush_runtime')) {
			wp_cache_flush_runtime();
		} elseif ( ! wp_using_ext_object_cache()) {
			wp_cache_flush();
		}
	}
}
