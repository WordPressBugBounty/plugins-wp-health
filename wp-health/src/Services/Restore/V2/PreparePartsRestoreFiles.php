<?php

namespace WPUmbrella\Services\Restore\V2;

use Coderatio\SimpleBackup\SimpleBackup;
use Exception;
use ZipArchive;

class PreparePartsRestoreFiles
{
	public function handle(string $filename, string $suffix = ''): array
	{
		/** @var RestorationDirectory $restorationDirectory */
		$restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);

		if ( ! $restorationDirectory->exists()) {
			$restorationDirectory->create();
		}

		$restorationPath = $restorationDirectory->getPath();

		try {

			$maxSize =  1048576 * 128; // 128MB

			@set_time_limit(0);

			$pathZip = sprintf("%s/%s", $restorationPath, $filename);

			$zip = new ZipArchive();
			$open = $zip->open($pathZip);
			if($open !== true) {
				return [
					'success' => false,
					'data' => [
						'code' => 'unzip_failed',
						'message' => 'Could not open zip file'
					]
				];
			}

			$maxFiles = $zip->numFiles;
			$iterator = 0;

			$part = 0;
			$size = 0;
			$filenameConfigPart = "backup-files-$suffix-part-%s.json";
			$filenameConfigCore = "backup-files-$suffix-part-core.json";
			$current = [];
			$configParts = [];

			$currentFilesCore = [];

			$filesInCore = $this->get_wp_files_core();

			do {

				$stat = $zip->statIndex($iterator);
				if(!$stat){
					$iterator++;
					continue;
				}

				// Don't restore umbrella files
				if(strpos($stat['name'], 'wp-health') !== false){
					$iterator++;
					continue;
				}

				$fileInCore = false;
				foreach ($filesInCore as $key => $file) {
					if($file['type'] === 'directory' && strpos($stat['name'], $file['path']) !== false){
						$fileInCore = true;
						break;
					}
					else if($file['type'] === 'file' && $stat['name'] === $file['path']){
						$fileInCore = true;
						break;
					}
				}

				if($fileInCore){
					$currentFilesCore[] = $stat['name'];
				}
				else{
					$size += $stat['size'];

					$current[] = $stat['name'];

					// Add part for new batch
					if( $size > $maxSize ){
						$filenamePart = sprintf('%s/%s', $restorationPath, sprintf($filenameConfigPart, $part));

						file_put_contents($filenamePart, json_encode($current));
						$configParts[] = $filenamePart;
						$size = 0;
						$part++;
						$current = [];
					}
				}

				$iterator++;

			} while($iterator < $maxFiles );

			// Last current
			$filenamePart = sprintf('%s/%s', $restorationPath, sprintf($filenameConfigPart, $part));
			file_put_contents($filenamePart, json_encode($current));
			$configParts[] = $filenamePart;

			// Core files
			$filenameCore = sprintf('%s/%s', $restorationPath, $filenameConfigCore);
			file_put_contents($filenameCore, json_encode($currentFilesCore));
			array_unshift($configParts, $filenameCore);

			$zip->close();

		} catch (Exception $e) {
			return [
				'success' => false,
				'data' => [
					'code' => 'unzip_failed',
					'message' => $e->getMessage()
				]
			];
		}

		return [
			'success' => true,
			'data' => [
				'parts' => $configParts,
				'filename' => $filename
			]
		];
	}

	private function get_wp_files_core(): array
	{
		return [
			[
				"type" => "directory",
				"path" => 'wp-admin/',
			],
			[
				"type" => "directory",
				"path" => 'wp-includes/'
			],
			[
				"type" => "file",
				"path" => '.htaccess',
			],
			[
				"type" => "file",
				"path" => 'index.php',
			],
			[
				"type" => "file",
				"path" => 'wp-config.php',
			],
			[
				"type" => "file",
				"path" => 'wp-comments-post.php',
			],
			[
				"type" => "file",
				"path" => 'wp-cron.php',
			],
			[
				"type" => "file",
				"path" => 'wp-load.php',
			],
			[
				"type" => "file",
				"path" => 'wp-login.php',
			],
			[
				"type" => "file",
				"path" => 'wp-mail.php',
			],
			[
				"type" => "file",
				"path" => 'wp-settings.php',
			],
			[
				"type" => "file",
				"path" => 'wp-signup.php',
			],
			[
				"type" => "file",
				"path" => 'wp-trackback.php',
			],
			[
				"type" => "file",
				"path" => 'xmlrpc.php',
			],
			[
				"type" => "file",
				"path" => 'wp-activate.php',
			],
			[
				"type" => "file",
				"path" => 'wp-blog-header.php',
			],
			[
				"type" => "file",
				"path" => 'wp-config-sample.php',
			],
			[
				"type" => "file",
				"path" => 'wp-links-opml.php',
			],
		];
	}

}
