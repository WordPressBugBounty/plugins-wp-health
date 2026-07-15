<?php
namespace WPUmbrella\Services\Security;

if (!defined('ABSPATH')) {
    exit;
}

class UpgradeContext
{
    const NAME_SERVICE = 'UpgradeContext';

    protected static $depth = 0;

    public function begin()
    {
        self::$depth++;
    }

    public function end()
    {
        if (self::$depth > 0) {
            self::$depth--;
        }
    }

    public function isActive()
    {
        return self::$depth > 0;
    }
}
