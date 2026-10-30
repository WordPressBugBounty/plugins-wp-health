<?php

namespace WPUmbrella\Services\Restore\V2;

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
}
