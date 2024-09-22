<?php

namespace WPUmbrella\Core\Scheduler;

use function ini_get;
use function memory_get_usage;
use function wp_convert_hr_to_bytes;
use function wp_raise_memory_limit;

trait MemoryLimit
{
	public function raiseMemoryLimit()
	{
		return wp_raise_memory_limit();
	}

	protected function getMemoryLimit(): int
	{
		if (function_exists('ini_get')) {
			$memory_limit = ini_get('memory_limit');
		} else {
			$memory_limit = '128M'; // Sensible default, and minimum required by WooCommerce
		}

		if ( ! $memory_limit || -1 === $memory_limit || '-1' === $memory_limit) {
			// Unlimited, set to 32GB.
			$memory_limit = '32G';
		}

		return wp_convert_hr_to_bytes($memory_limit);
	}

	public function memoryExceeded(): bool
	{
		return memory_get_usage(true) >= $this->getMemoryLimit() * 0.9;
	}
}
