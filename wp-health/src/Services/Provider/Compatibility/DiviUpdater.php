<?php
namespace WPUmbrella\Services\Provider\Compatibility;

class DiviUpdater
{
    public function register()
    {
        if (!function_exists('et_register_updates_component')) {
            return;
        }

        try {
            if (did_action('init')) {
                et_register_updates_component();
            } else {
                add_action('init', 'et_register_updates_component');
            }
        } catch (\Exception $e) {
            // Best-effort.
        }
    }
}
