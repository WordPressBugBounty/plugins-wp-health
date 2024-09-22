<?php

namespace WPUmbrella\Controller\Restore\V2;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Restore\V2\DownloadUrlWithFilename;

class RestoreDownload extends AbstractController
{
	public function executePost($params)
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

		if ( ! isset($params['url'])) {
			return $this->returnResponse([
				'success' => false,
				'data'    => [
					'code'    => 'missing_parameters',
					'message' => '"url" parameter is missing',
				],
			], 400);
		}

		/** @var DownloadUrlWithFilename $downloadUrlWithFilename */
		$downloadUrlWithFilename  = wp_umbrella_get_service(DownloadUrlWithFilename::class);
		$response = $downloadUrlWithFilename->handle($params['url'], $params['filename']);

		$status = $response['success'] ? 200 : 400;

		return $this->returnResponse($response, $status);
	}
}
