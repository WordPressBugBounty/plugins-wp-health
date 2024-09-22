<?php

namespace WPUmbrella\Models\Backup;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

interface BackupBuilder
{
	public function reset();
	public function buildFileSource($options = []);
	public function buildSqlSource($options = []);
	public function buildProcessor($options =[]);
	public function buildDestination($options =[]);
	public function buildProfile();
}
