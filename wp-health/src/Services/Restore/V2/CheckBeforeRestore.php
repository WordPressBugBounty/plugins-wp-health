<?php

namespace WPUmbrella\Services\Restore\V2;

use function disk_free_space;
use function function_exists;
use function wp_is_writable;

class CheckBeforeRestore
{

	public function handle($options): array
	{
		$downloadSize = $options['download_size'] ?? 0;
		$type = $options['type'] ?? 'files';

		/** @var RestorationDirectory $restorationDirectory */
		$restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);

		if ( ! wp_is_writable($restorationDirectory->getParentPath())) {
			return [
				'success' => false,
				'data'    => [
					'code'    => 'check_root_path_is_not_writable',
					'message' => 'Root path is not writable.',
				],
			];
		}

		// Restoration in progress.
		if ($restorationDirectory->exists() && $type === 'files') {
			return [
				'success' => false,
				'data'    => [
					'code'    => 'check_restoration_in_progress',
					'message' => 'Restoration already in progress.',
				],
			];
		}

		if ( ! $this->hasDiskFreeSpace($restorationDirectory->getParentPath(), $downloadSize)) {
			return [
				'success' => false,
				'data'    => [
					'code'    => 'check_disk_space_is_full',
					'message' => 'Disk space is full.',
				],
			];
		}

		return [
			'success' => true,
			'data'    => [],
		];;
	}

	private function hasDiskFreeSpace(string $directory, int $limit): bool
	{
		if (function_exists('disk_free_space')) {
			$freeSpace = disk_free_space($directory);

			return $freeSpace >= $limit;
		}

		return true;
	}
}
