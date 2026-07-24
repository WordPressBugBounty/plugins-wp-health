<?php
namespace WPUmbrella\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WebServer
{
    public function getType()
    {
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';

        if (strpos($software, 'apache') !== false) {
            return 'apache';
        }

        if (strpos($software, 'nginx') !== false) {
            return 'nginx';
        }

        return 'unknown';
    }

    public function isNginx()
    {
        return $this->getType() === 'nginx';
    }
}
