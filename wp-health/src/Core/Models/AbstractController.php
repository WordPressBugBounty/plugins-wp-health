<?php
namespace WPUmbrella\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\Controller;
use WPUmbrella\Core\Models\TraitApiController;
use WPUmbrella\Core\Models\TraitPhpController;
use WP_REST_Request;
use WP_REST_Response;

abstract class AbstractController
{
    use TraitApiController;
    use TraitPhpController;

    protected $options;

    public function __construct($options)
    {
        $this->options = $options;
    }

    public function getFrom()
    {
        return isset($this->options['from']) ? $this->options['from'] : null;
    }

    public function getVersion()
    {
        return isset($this->options['version']) ? $this->options['version'] : 'v1';
    }

    public function getRoute()
    {
        return isset($this->options['route']) ? $this->options['route'] : null;
    }

    public function getMethod()
    {
        return isset($this->options['method']) ? $this->options['method'] : null;
    }

    public function getPermission()
    {
        return isset($this->options['permission']) ? $this->options['permission'] : null;
    }

    public function getNeedAdministrator()
    {
        return isset($this->options['need_administrator']) ? $this->options['need_administrator'] : false;
    }

    public function execute()
    {
        switch ($this->getFrom()) {
            case Controller::API:
            default:
                $this->executeApi();
                break;
            case Controller::PHP:
                $this->executePhp();
                break;
        }
    }

    public function returnResponse($data, $status = 200)
    {
        wp_umbrella_get_service('SessionStore')->removeUmbrellaSessions();

        $from = $this->getFrom();

        switch ($from) {
            case Controller::API:
                return $this->getResponseApi($data, $status);
                break;
            case Controller::PHP:
                return $this->getResponsePhp($data, $status);
                break;
        }
    }
}
