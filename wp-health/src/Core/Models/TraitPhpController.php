<?php
namespace WPUmbrella\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Controller;
use WPUmbrella\Core\UmbrellaRequest;

trait TraitPhpController
{
    public function getCallbackPhp()
    {
        $method = $this->getMethod();

        switch ($method) {
            case 'GET':
                return 'getPhp';
                break;
            case 'POST':
                return 'postPhp';
                break;
            case 'PUT':
                return 'putPhp';
                break;
            case 'DELETE':
                return 'deletePhp';
                break;
        }
    }

    protected function executePhp()
    {
        $callback = $this->getCallbackPhp();

        try {
            $authorize = $this->permissionPhp();
            if (!$authorize) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        if (!\method_exists($this, $callback)) {
            return;
        }

        $this->$callback();
    }

    protected function getParameters()
    {
        $method = $this->getMethod();

        if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data === null) {
                return $_POST;
            }
            return $data;
        } elseif ($method === 'GET') {
            return $_GET;
        }
    }

    public function getPhp()
    {
        $params = $this->getParameters();
        return $this->executeGet($params);
    }

    public function postPhp()
    {
        $params = $this->getParameters();
        return $this->executePost($params);
    }

    public function putPhp()
    {
        $params = $this->getParameters();
        return $this->executePut($params);
    }

    public function deletePhp()
    {
        $params = $this->getParameters();
        return $this->executeDelete($params);
    }

    protected function getHeaders()
    {
        $function = 'getallheaders';
        $headers = [];

        if (function_exists($function)) {
            $headers = $function();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $name = substr($name, 5);
                    $name = str_replace('_', ' ', $name);
                    $name = strtolower($name);
                    $name = ucwords($name);
                    $name = str_replace(' ', '-', $name);

                    $headers[$name] = $value;
                } elseif ($function === 'apache_request_headers') {
                    $headers[$name] = $value;
                }
            }
        }

        return array_change_key_case($headers, CASE_LOWER);
    }

    public function permissionPhp()
    {
        $method = $this->getMethod();
        $permission = $this->getPermission();

        if (empty($permission)) {
            return true;
        }

        if (isset($this->options['prevent_active']) && $this->options['prevent_active']) {
            $this->preventNotActive();
        }

        $request = UmbrellaRequest::createFromGlobals();

        switch ($permission) {
            case Controller::PERMISSION_ONLY_API_TOKEN:
                return wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isOnlyTokenAuthorized($request);
                break;
            case Controller::PERMISSION_WITH_SECRET_TOKEN:
                return wp_umbrella_get_service('RequestPermissionsByUmbrellaRequest')->isFullyAuthorized($request);
                break;
            default:
                return true;
        }

        return true;
    }

    protected function preventNotActive()
    {
        if (!function_exists('is_plugin_active') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active('wp-health/wp-health.php')) {
            return;
        }

        $this->returnResponse([
            'code' => 'not_authorized'
        ], 403);
        return;
    }

    public function getResponsePhp($data, $status = 200)
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: application/json');

        http_response_code($status);
        if ($status !== 200) {
            status_header($status);
        }

        echo json_encode($data);
        exit;
    }
}
