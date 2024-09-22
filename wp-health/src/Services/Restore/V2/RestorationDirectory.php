<?php

namespace WPUmbrella\Services\Restore\V2;

use Coderatio\SimpleBackup\SimpleBackup;
use Exception;

class RestorationDirectory
{
	const OPTION = 'wp_umbrella_restoration_suffix_security';

	public function getHashedFolder(): string
	{
		return get_option(self::OPTION, '');
	}

	public function getParentPath(): string
	{
		return WP_UMBRELLA_DIR_WPU_RESTORE;
	}

	public function getPath(): string
	{
		return sprintf("%s/%s", $this->getParentPath(), $this->getHashedFolder());
	}

	public function exists(): bool
	{
		return ! empty($this->getHashedFolder()) && file_exists($this->getPath());
	}

	public function generateHash(): string
	{
		$directorySuffix = sprintf('umbrella-%s', bin2hex(random_bytes(5)));
		update_option(self::OPTION, $directorySuffix, false);

		return $directorySuffix;
	}

	public function create(): bool
	{
		$hashedDirectory = sprintf("%s/%s", $this->getParentPath(), $this->generateHash());

		return wp_mkdir_p($hashedDirectory);
	}

	public function existSecureFile(): bool {

		return file_exists(sprintf("%s/%s", $this->getParentPath(), 'security.php'));
	}

	public function createSecureFile(): bool
	{
		$apiKey = wp_umbrella_get_api_key();
		$secretToken = wp_umbrella_get_secret_token();

		$current = "<?php
		if(!defined('WP_UMBRELLA_API_KEY')) {
			define('WP_UMBRELLA_API_KEY', '{$apiKey}');
		}
		define('WP_UMBRELLA_SECRET_TOKEN', '{$secretToken}');
		define('WP_UMBRELLA_REQUEST_VERSION', 'v2');
		";

		return file_put_contents(sprintf("%s/%s", $this->getParentPath(), 'security.php'), $current);
	}

	public function loadSecureFile() {
		if(!$this->existSecureFile()){
			return;
		}
		require_once sprintf("%s/%s", $this->getParentPath(), 'security.php');
	}

	public function removeSecureFile(): bool
	{
		return wp_umbrella_remove_file(sprintf("%s/%s", $this->getParentPath(), 'security.php'));
	}
}
