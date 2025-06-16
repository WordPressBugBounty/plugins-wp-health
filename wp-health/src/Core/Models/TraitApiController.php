<?php
namespace WPUmbrella\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Controller;
use WPUmbrella\Core\UmbrellaRequest;
use WP_REST_Request;
use WP_REST_Response;

trait TraitApiController
{
    public function getCallbackApi()
    {
        $method = $this->getMethod();

        switch ($method) {
            case 'GET':
                return 'getApi';
                break;
            case 'POST':
                return 'postApi';
                break;
            case 'PUT':
                return 'putApi';
                break;
            case 'DELETE':
                return 'deleteApi';
                break;
        }
    }

    protected function executeApi()
    {
        $route = $this->getRoute();
        $method = $this->getMethod();
        $callback = $this->getCallbackApi();
        $version = $this->getVersion();

        register_rest_route(sprintf('wp-umbrella/%s', $version), $route, [
            'methods' => $method,
            'callback' => [$this, $callback],
            'permission_callback' => [$this, 'permissionApi'],
        ]);
    }

    public function getApi(WP_REST_Request $request)
    {
        $params = $request->get_params();
        return $this->executeGet($params);
    }

    public function postApi(WP_REST_Request $request)
    {
        $params = $request->get_params();
        return $this->executePost($params);
    }

    public function putApi(WP_REST_Request $request)
    {
        $params = $request->get_params();
        return $this->executePut($params);
    }

    public function deleteApi(WP_REST_Request $request)
    {
        $params = $request->get_params();
        return $this->executeDelete($params);
    }

    public function permissionApi($request)
    {
        $permission = $this->getPermission();

        if (empty($permission)) {
            return true;
        }

        $umbrellaRequest = UmbrellaRequest::createFromGlobals();

        switch ($permission) {
            case Controller::PERMISSION_ONLY_API_TOKEN:
                return wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isOnlyTokenAuthorized($umbrellaRequest);
                break;
            case Controller::PERMISSION_WITH_SECRET_TOKEN:
                return wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isFullyAuthorized($umbrellaRequest);
                break;
            default:
                return true;
        }
    }

    public function getResponseApi($data, $status = 200)
    {
        $restResponse = new WP_REST_Response($data, $status);

        $restResponse->set_headers([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
        return $restResponse;
    }
}
