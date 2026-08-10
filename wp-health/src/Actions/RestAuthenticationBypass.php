<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ExecuteHooks;

class RestAuthenticationBypass implements ExecuteHooks
{
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
        $route = $this->getResolvedRoute();

        return $route !== '' && strpos($route, self::REST_ROUTE_PREFIX) === 0;
    }

    /**
     * @return string
     */
    protected function getResolvedRoute()
    {
        if (isset($GLOBALS['wp']) && !empty($GLOBALS['wp']->query_vars['rest_route'])) {
            return '/' . ltrim((string) $GLOBALS['wp']->query_vars['rest_route'], '/');
        }

        $path = isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : '';
        if ($path !== '') {
            return '/' . ltrim($path, '/');
        }

        return '';
    }
}
