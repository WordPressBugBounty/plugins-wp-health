<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;

class RestAuthenticationBypass implements ExecuteHooks
{
    const ROUTE_PREFIX = '/wp-json/wp-umbrella/';

    const REST_ROUTE_PREFIX = '/wp-umbrella/';

    public function hooks()
    {
        add_filter('rest_authentication_errors', [$this, 'bypass'], PHP_INT_MAX);
    }

    public function bypass($result)
    {
        if (!$this->isUmbrellaRoute()) {
            return $result;
        }

        return null;
    }

    protected function isUmbrellaRoute()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && strpos($uri, self::ROUTE_PREFIX) !== false) {
            return true;
        }

        $restRoute = isset($_GET['rest_route']) ? (string) $_GET['rest_route'] : '';
        return $restRoute !== '' && strpos($restRoute, self::REST_ROUTE_PREFIX) === 0;
    }
}
