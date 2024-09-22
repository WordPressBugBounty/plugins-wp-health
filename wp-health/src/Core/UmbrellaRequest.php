<?php
namespace WPUmbrella\Core;

use WPUmbrella\Services\Restore\V2\RestorationDirectory;
use WPUmbrella\Helpers\Controller;

class UmbrellaRequest
{
    protected $checkTypeQuery = null;

    protected $method = null;

    protected $query = [];

    protected $request = [];

    protected $headers = [];

    /**
     * @var array
     */
    public function __construct($options = [])
    {
        $this->query = $options['query'] ?? [];
        $this->request = $options['request'] ?? [];
        $this->headers = $options['headers'] ?? [];
    }

    public function getMethod()
    {
        if (null === $this->method) {
            $this->method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }

        return $this->method;
    }

    public static function createFromGlobals()
    {
        $request = new self([
            'query' => $_GET,
            'request' => $_POST,
            'headers' => wp_umbrella_get_headers(),
        ]);

        $request->setTypeQuery();

        $action = $request->getAction();

        if ($action && strpos($action, '/v1/restores') === 0) {
            $restorationDirectory = wp_umbrella_get_service(RestorationDirectory::class);
            $restorationDirectory->loadSecureFile();
        }

        return $request;
    }

    protected function setTypeQuery()
    {
        if (isset($this->headers['x-umbrella'])) {
            $this->checkTypeQuery = 'headers';
        }

        if (isset($this->request['x-umbrella'])) {
            $this->checkTypeQuery = 'post';
        }

        if (isset($this->query['x-umbrella'])) {
            $this->checkTypeQuery = 'get';
        }
    }

    public function canTryExecuteWPUmbrella()
    {
        if ($this->checkTypeQuery === null) {
            return false;
        }

        return true;
    }

    public function getAction()
    {
        $value = null;
        switch($this->checkTypeQuery) {
            case 'headers':
                $value = isset($this->headers['x-action']) ? $this->headers['x-action'] : null;
                break;
            case 'post':
                $value = isset($this->request['x-action']) ? $this->request['x-action'] : null;
                break;
            case 'get':
                $value = isset($this->query['x-action']) ? $this->query['x-action'] : null;
                break;
            default:
                return null;
        }

        try {
            if (!$value) {
                $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
                $requestUri = parse_url($requestUri, PHP_URL_PATH);

                $value = str_replace('/wp-json/wp-umbrella', '', $requestUri);
            }
        } catch (\Exception $e) {
            // Do nothing
        }

        return  $value;
    }

    public function getRequestFrom()
    {
        try {
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
            $requestUri = parse_url($requestUri, PHP_URL_PATH);

            // If contain wp-json/wp-umbrella, from API
            if (strpos($requestUri, 'wp-json/wp-umbrella') !== false) {
                return Controller::API;
            }
            return Controller::PHP;
        } catch (\Exception $e) {
            return Controller::PHP;
        }
    }

    public function getRequestVersion()
    {
        switch($this->checkTypeQuery) {
            case 'headers':
                return isset($this->headers['x-request-version']) ? $this->headers['x-request-version'] : 'v1';
            case 'post':
                return isset($this->request['x-request-version']) ? $this->request['x-request-version'] : 'v1';
            case 'get':
                return isset($this->query['x-request-version']) ? $this->query['x-request-version'] : 'v1';
            default:
                return 'v1';
        }
    }

    public function getParam($name)
    {
        switch($this->checkTypeQuery) {
            case 'headers':
                return isset($this->headers[$name]) ? $this->headers[$name] : null;
            case 'post':
                return isset($this->request[$name]) ? $this->request[$name] : null;
            case 'get':
                return isset($this->query[$name]) ? $this->query[$name] : null;
            default:
                return null;
        }
    }

    public function getSecretToken()
    {
        $value = null;

        switch($this->checkTypeQuery) {
            case 'headers':
                if (isset($this->headers['x-secret-token'])) {
                    $value = $this->headers['x-secret-token'];
                }
                if (isset($this->headers['x-auth-token'])) {
                    $value = $this->headers['x-auth-token'];
                }
                break;
            case 'post':
                if (isset($this->request['x-secret-token'])) {
                    $value = $this->request['x-secret-token'];
                }
                if (isset($this->request['x-auth-token'])) {
                    $value = $this->request['x-auth-token'];
                }
                break;
            case 'get':
                if (isset($this->query['x-secret-token'])) {
                    $value = $this->query['x-secret-token'];
                }
                if (isset($this->query['x-auth-token'])) {
                    $value = $this->query['x-auth-token'];
                }
                break;
            default:
                $value = null;
                break;
        }

        return wp_umbrella_get_service('WordPressContext')->getHash($value);
    }

    public function getToken()
    {
        switch($this->checkTypeQuery) {
            case 'headers':
                return $this->headers['x-umbrella'];
            case 'post':
                return $this->request['x-umbrella'];
            case 'get':
                return $this->query['x-umbrella'];
            default:
                return null;
        }
    }
}
