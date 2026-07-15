<?php
namespace WPUmbrella\Actions\Hardening\LoginGuard;

use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class ClearTransientGuard implements ExecuteHooks
{
    public function hooks()
    {
        add_action('wp_umbrella_clear_cache', [$this, 'reset']);
        add_action('update_option_' . \WPUmbrella\Services\HardeningSettings::OPTION_KEY, [$this, 'reset']);
    }

    public function reset()
    {
        (new FilterStorage())->clear();
    }
}
