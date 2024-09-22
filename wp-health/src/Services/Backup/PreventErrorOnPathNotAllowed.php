<?php

namespace WPUmbrella\Services\Backup;

use Exception;

class PreventErrorOnPathNotAllowed
{

	const OPTION_NAME = 'wpumbrella_backup_path_not_allowed';

	public function execute(Exception $error){
		try {
			$pattern = '/File\((.*?)\) is not within the allowed path\(s\)/';

			preg_match($pattern, $error->getMessage(), $matches);

			if (!isset($matches[1])) {
				return false;
			}

			$inaccessiblePath = $matches[1];
			$baseDirectory = dirname($inaccessiblePath);

			$data = get_option(self::OPTION_NAME);

			if(!$data){
				$data = [];
			}

			if(in_array($baseDirectory, $data)){
				return false;
			}

			$data[] = $baseDirectory;

			update_option(self::OPTION_NAME, $data, false);

			return true;

		} catch (Exception $e) {
			// No need to do anything
		}
	}

	public function getInaccessiblePaths(){
		$data = get_option(self::OPTION_NAME);
		if(!$data){
			$data = [];
		}

		return $data;
	}
}
