<?php

namespace WPUmbrella\Core\Hooks;

defined('ABSPATH') or die('Cheatin&#8217; uh?');

interface DeactivationHook
{
    public function deactivate();
}
