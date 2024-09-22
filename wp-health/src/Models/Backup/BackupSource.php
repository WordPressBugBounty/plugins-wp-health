<?php

namespace WPUmbrella\Models\Backup;

interface BackupSource
{
	public function fetch($scratchDir);
}
