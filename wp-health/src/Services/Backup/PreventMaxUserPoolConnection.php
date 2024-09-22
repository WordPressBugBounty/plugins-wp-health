<?php

namespace WPUmbrella\Services\Backup;

use WPUmbrella\Helpers\DataTemporary;
use Exception;


class PreventMaxUserPoolConnection
{
	public function execute(Exception $error){
		try {
			$regex = "/max_user_connections/";

			if (preg_match($regex, $error->getMessage())) {
				DataTemporary::setDataByKey('code_error_backup', 'database_max_user_connections');
				DataTemporary::setDataByKey('message_error_backup', $error->getMessage());
				return false;
			}

			return true;


		} catch (Exception $e) {
			// No need to do anything
		}
	}

}
