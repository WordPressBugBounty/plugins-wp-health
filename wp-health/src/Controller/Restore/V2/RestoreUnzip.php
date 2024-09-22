<?php

namespace WPUmbrella\Controller\Restore\V2;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Restore\V2\UnzipFilename;

class RestoreUnzip extends AbstractController
{
	public function executePost(array $params)
	{

		if ( ! isset($params['filename'])) {
			return $this->returnResponse([
				'success' => false,
				'data'    => [
					'code'    => 'missing_parameters',
					'message' => '"filename" parameter is missing',
				],
			], 400);
		}

		$type          = isset($params['type']) ? $params['type'] : 'files';
		$fileToExtract = isset($params['file_to_extract']) ? $params['file_to_extract'] : null;
		$directory     = isset($params['directory']) ? $params['directory'] : '';
		$options       = [
			'directory'       => $directory,
			'file_to_extract' => $fileToExtract,
		];

		/** @var UnzipFilename $unzipFilename */
		$unzipFilename  = wp_umbrella_get_service(UnzipFilename::class);
		$response = $unzipFilename->handle($params['filename'], $type, $options);

		$status = $response['success'] ? 200 : 400;

		return $this->returnResponse($response, $status);
	}
}
