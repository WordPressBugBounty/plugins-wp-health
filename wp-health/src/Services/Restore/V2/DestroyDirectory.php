<?php

namespace WPUmbrella\Services\Restore\V2;

use Exception;

class DestroyDirectory
{
	public function handle(string $dir): bool
	{
		try {
			if (!is_dir($dir) || is_link($dir)) {
				return unlink($dir);
			}

			foreach (scandir($dir) as $file) {
				if ($file == '.' || $file == '..') {
					continue;
				}
				if (!$this->handle($dir . DIRECTORY_SEPARATOR . $file)) {
					chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
					if (!$this->handle($dir . DIRECTORY_SEPARATOR . $file)) {
						return false;
					}
				};
			}
			return rmdir($dir);
		} catch (Exception $e) {
			return false;
		}
	}
}


