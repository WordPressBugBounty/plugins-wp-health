<?php

namespace WPUmbrella\Services\Restore\V2;

use Exception;

class DownloadUrlWithFilename
{

	public function handle(string $url, string $filename): array
	{
		/** @var RestorationDirectory $restorationDirectory */
		$restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);

		if( ! $restorationDirectory->exists()){
			$restorationDirectory->create();
		}

		$restorationPath = $restorationDirectory->getPath();

		try {
			$path = sprintf("%s/%s", $restorationPath, $filename);

			/** @var CopyFileChunked $copyFileChunked */
			$copyFileChunked = wp_umbrella_get_service(CopyFileChunked::class);
			$result          = $copyFileChunked->handle($url, $path);

			if ( ! $result) {
				return [
					'success' => false,
					'data'    => [
						'code' => 'download_failed',
					],
				];
			}
		} catch (Exception $e) {

			return [
				'success' => false,
				'data'    => [
					'code'    => 'download_failed',
					'message' => $e->getMessage(),
				],
			];
		}

		return [
			"success" => true,
			"data"    => [
				"filename" => $filename,
			],
		];
	}
}
