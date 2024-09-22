<?php

namespace WPUmbrella\Services\Backup;

use WPUmbrella\Helpers\DataTemporary;
use Exception;


class PreventNoTableFound
{
	public function execute(Exception $error){
		try {
			if (strpos(strtolower($error->getMessage()), "no tables found") !== false) {
				return true;
			}

			return false;


		} catch (Exception $e) {
			// No need to do anything
		}
	}

}
