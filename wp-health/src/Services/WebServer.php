<?php
namespace WPUmbrella\Services;

use WPUmbrella\Services\Security\StaticFileProtectionProbe;

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

    public function getEdgeType()
    {
        $cached = get_transient(StaticFileProtectionProbe::TRANSIENT);

        if (!is_array($cached) || empty($cached['server'])) {
            return 'unknown';
        }

        return $cached['server'];
    }

    public function isReverseProxied()
    {
        $edge = $this->getEdgeType();

        if ($edge === 'unknown') {
            return false;
        }

        return $edge !== $this->getType();
    }
}
