<?php

namespace WPUmbrella\Services\Restore\V2;

use Exception;
use ZipArchive;

use function file_exists;
use function file_get_contents;
use function json_decode;
use function mkdir;
use function sprintf;

class UnzipFilename
{

	public function handle(string $filename, string $type, $options = []): array
	{
		/** @var RestorationDirectory $restorationDirectory */
		$restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);
		$restorationPath = $restorationDirectory->getPath();

		$directory     = isset($options['directory']) ? $options['directory'] : '';
		$fileToExtract = isset($options['file_to_extract']) ? $options['file_to_extract'] : null;

		$pathZip = sprintf("%s/%s", $restorationPath, $filename);

		if ($type === 'database') {
			if (empty($directory)) {
				$directory = 'database';
			}
			$pathUnzip = sprintf("%s/%s", $restorationPath, $directory);

			if ( ! file_exists($pathUnzip)) {
				mkdir($pathUnzip, 0777, true);
			}
		} else {
			// Unzip at WordPress root
			$pathUnzip = sprintf("%s/", ABSPATH);
		}


		try {
			$zip  = new ZipArchive();
			$open = $zip->open($pathZip);

			if ($open !== true) {
				return [
					'success' => false,
					'data'    => [
						'code'    => 'unzip_failed',
						'message' => 'Could not open zip file',
					],
				];
			}

			if ($fileToExtract !== null && file_exists($fileToExtract)) {
				$items = json_decode(file_get_contents($fileToExtract), true);
				$zip->extractTo($pathUnzip, $items);

				if (file_exists($fileToExtract)) {
					@unlink($fileToExtract);
				}
			} else {
				$zip->extractTo($pathUnzip);
			}
			$zip->close();
		} catch (Exception $e) {
			return [
				'success' => false,
				'data'    => [
					'code'    => 'unzip_failed',
					'message' => $e->getMessage(),
				],
			];
		}

		$files = [];
		if ($type === 'database') {
			$files = $this->getFilesInDirectoryRecursive($pathUnzip);
		}

		return [
			'success' => true,
			'data'    => [
				'files'    => $files,
				'filename' => $filename,
			],
		];
	}

	protected function getFilesInDirectoryRecursive(string $path): array
	{
		$files  = array_diff(scandir($path), array('.', '..'));
		$result = [];
		foreach ($files as $file) {
			if (is_dir($path.'/'.$file)) {
				$result = array_merge($result, $this->getFilesInDirectoryRecursive($path.'/'.$file));
			} else {
				$result[] = $path.'/'.$file;
			}
		}

		return $result;
	}

}
