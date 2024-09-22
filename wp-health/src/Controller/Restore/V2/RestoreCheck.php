<?php

namespace WPUmbrella\Controller\Restore\V2;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Restore\V2\CheckBeforeRestore;
use WPUmbrella\Services\Restore\V2\RestorationDirectory;

class RestoreCheck extends AbstractController
{
	public function executeGet($params)
	{
		/** @var CheckBeforeRestore $check */
		$checkBeforeRestore = wp_umbrella_get_service(CheckBeforeRestore::class);
		$downloadSize       = $params['download_size'] ?? 0;
		$type       		= $params['type'] ?? 'files';
		$response           = $checkBeforeRestore->handle([
			'download_size' => $downloadSize,
			'type' => $type,
		]);



		$status = $response['success'] ? 200 : 400;

		if($status === 200){
			/** @var RestorationDirectory $restorationDirectory */
			$restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);

			if(! $restorationDirectory->existSecureFile()){
				$restorationDirectory->createSecureFile();
			}
		}

		return $this->returnResponse($response, $status);
	}
}
