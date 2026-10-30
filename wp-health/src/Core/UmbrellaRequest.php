<?php
namespace WPUmbrella\Core;

use WPUmbrella\Core\Constants\SignedRequest;
use WPUmbrella\Helpers\Controller;

class UmbrellaRequest
{
    const REST_ROOT = '/wp-json';

    const REST_NAMESPACE = '/wp-umbrella';

    protected $checkTypeQuery = null;

    protected $method = null;

    protected $query = [];

    protected $request = [];

    protected $headers = [];

    protected $dispatchedRoute = null;

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

        return $request;
    }

    protected function setTypeQuery()
    {
        if (isset($this->headers['authorization'])) {
            $bearer = wp_umbrella_get_service('BearerTokenExtractor')->fromHeaderValue(
                $this->headers['authorization']
            );
            if ($bearer !== null) {
                $this->checkTypeQuery = 'headers';
            }
        }

        if (isset($this->headers['x-secret-token'])) {
            $bearer = wp_umbrella_get_service('BearerTokenExtractor')->fromHeaderValue(
                $this->headers['x-secret-token']
            );
            if ($bearer !== null) {
                $this->checkTypeQuery = 'headers';
            }
        }

        if (isset($this->headers['x-umbrella'])) {
            $this->checkTypeQuery = 'headers';
        }

        if (isset($this->request['x-umbrella'])) {
            $this->checkTypeQuery = 'post';
        }

        if (isset($this->query['x-umbrella'])) {
            $this->checkTypeQuery = 'get';
        }

        if ($this->checkTypeQuery === null && isset($this->request['x-umb-login-sig'])) {
            $this->checkTypeQuery = 'post';
        }

        if ($this->checkTypeQuery === null && isset($this->query['x-umb-login-sig'])) {
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

    protected function hasSignatureHeaders()
    {
        return isset($this->headers[strtolower(SignedRequest::SIGNATURE_V2_HEADER)])
            || isset($this->headers[strtolower(SignedRequest::SIGNATURE_HEADER)]);
    }

    public function getAction()
    {
        $value = null;
        $source = $this->checkTypeQuery !== null && $this->hasSignatureHeaders()
            ? 'headers' : $this->checkTypeQuery;

        switch($source) {
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

    public function setDispatchedRoute($route)
    {
        $this->dispatchedRoute = $route;
    }

    /**
     * @return boolean
     */
    public function isLoginRoute()
    {
        return $this->getAction() === '/v1/login' && $this->requestedRoutesMatchAction();
    }

    /**
     * @return boolean
     */
    public function requestedRoutesMatchAction()
    {
        $action = $this->getAction();

        foreach ($this->getRequestedRoutes() as $route) {
            if ($route !== $action) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getRequestedRoutes()
    {
        $routes = [];

        $candidates = [
            $this->dispatchedRoute,
            $this->request['rest_route'] ?? null,
            $this->query['rest_route'] ?? null,
        ];

        foreach ($candidates as $route) {
            if ($route === null) {
                continue;
            }

            $routes[] = is_string($route) ? $this->normalizeRoute($route) : '';
        }

        foreach ($this->getRoutingPaths() as $path) {
            if (preg_match('#' . preg_quote(self::REST_ROOT, '#') . '(/.*)?$#i', $path, $matches)) {
                $routes[] = $this->normalizeRoute(isset($matches[1]) ? $matches[1] : '');
            }
        }

        return $routes;
    }

    /**
     * @return string[]
     */
    protected function getRoutingPaths()
    {
        $paths = [];

        $candidates = [
            isset($_SERVER['REQUEST_URI']) ? explode('?', (string) $_SERVER['REQUEST_URI'], 2)[0] : null,
            isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : null,
        ];

        foreach ($candidates as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $paths[] = preg_replace('#/+#', '/', rawurldecode($path));
        }

        return $paths;
    }

    protected function normalizeRoute($route)
    {
        $route = untrailingslashit($route);

        if (strpos($route, self::REST_NAMESPACE . '/') === 0) {
            return substr($route, strlen(self::REST_NAMESPACE));
        }

        return $route;
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
                return isset($this->headers['x-umbrella']) ? $this->headers['x-umbrella'] : null;
            case 'post':
                return isset($this->request['x-umbrella']) ? $this->request['x-umbrella'] : null;
            case 'get':
                return isset($this->query['x-umbrella']) ? $this->query['x-umbrella'] : null;
            default:
                return null;
        }
    }

    /**
     * Return the Bearer token carried by the Authorization header, or null
     * when the header is absent, malformed, or carries another scheme.
     *
     * Always read from request headers — never from POST/GET params — so that
     * tokens cannot leak through query strings or form bodies.
     *
     * @return string|null
     */
    public function getAuthorizationBearer()
    {
        $extractor = wp_umbrella_get_service('BearerTokenExtractor');

        $value = isset($this->headers['authorization']) ? $this->headers['authorization'] : null;
        $bearer = $extractor->fromHeaderValue($value);
        if ($bearer !== null) {
            return $bearer;
        }

        $fallback = isset($this->headers['x-secret-token']) ? $this->headers['x-secret-token'] : null;
        return $extractor->fromHeaderValue($fallback);
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getRequestPath()
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;

        if ($requestUri === null) {
            return null;
        }

        return parse_url($requestUri, PHP_URL_PATH);
    }

    public function getRequestQuery()
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;

        if ($requestUri === null) {
            return '';
        }

        $query = parse_url($requestUri, PHP_URL_QUERY);

        return is_string($query) ? $query : '';
    }

    public function getActionHeader()
    {
        return isset($this->headers['x-action']) ? (string) $this->headers['x-action'] : '';
    }

    public function getRawBody()
    {
        $body = file_get_contents('php://input');

        return $body === false ? '' : $body;
    }
}
