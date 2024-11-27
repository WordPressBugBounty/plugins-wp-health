<?php
namespace WPUmbrella\Controller\Restore\V2;

use WP_REST_Response;
use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Restore\V2\Cleanup;
use WPUmbrella\Services\Restore\V2\PreparePartsRestoreFiles;

class RestoreCleanup extends AbstractController
{
    public function executePost($params): WP_REST_Response
    {
        /** @var Cleanup $cleanup */
        $cleanup = wp_umbrella_get_service(Cleanup::class);
        $response = $cleanup->handle();

        $status = $response['success'] ? 200 : 400;

        return $this->returnResponse($response, $status);
    }
}
