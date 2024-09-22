<?php

namespace WPUmbrella\Controller\Restore\V2;

use WP_REST_Response;
use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Restore\V2\PreparePartsRestoreFiles;

class PrepareRestoreFiles extends AbstractController
{
	public function executePost($params): WP_REST_Response
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

		if ( ! isset($params['suffix'])) {
			return $this->returnResponse([
				'success' => false,
				'data'    => [
					'code'    => 'missing_parameters',
					'message' => '"suffix" parameter is missing',
				],
			], 400);
		}

		/** @var PreparePartsRestoreFiles $preparePartsRestoreFiles */
		$preparePartsRestoreFiles = wp_umbrella_get_service(PreparePartsRestoreFiles::class);
		$response                 = $preparePartsRestoreFiles->handle($params['filename'], $params['suffix']);

		$status = $response['success'] ? 200 : 400;

		return $this->returnResponse($response, $status);
	}
}
