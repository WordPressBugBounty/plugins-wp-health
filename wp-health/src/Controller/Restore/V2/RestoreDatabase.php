<?php
namespace WPUmbrella\Controller\Restore\V2;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Restore\V2\RestoreDatabase as RestoreDatabaseService;

class RestoreDatabase extends AbstractController
{
    public function executePost(array $params)
    {
        if (!isset($params['table'])) {
            return $this->returnResponse([
                'success' => false,
                'data' => [
                    'code' => 'missing_parameters',
                    'message' => '"table" parameter is missing.',
                ],
            ], 400);
        }

        /** @var RestoreDatabaseService $restoreDatabase */
        $restoreDatabase = wp_umbrella_get_service(RestoreDatabaseService::class);
        $response = $restoreDatabase->handle($params['table']);

        $status = $response['success'] ? 200 : 400;

        return $this->returnResponse($response, $status);
    }
}
