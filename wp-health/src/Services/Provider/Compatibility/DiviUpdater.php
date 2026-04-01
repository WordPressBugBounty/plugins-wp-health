<?php
namespace WPUmbrella\Services\Provider\Compatibility;

class DiviUpdater
{
    /**
     * Register Divi's update component if available.
     *
     * Divi registers its update system on `init`, but our pipeline may execute
     * before that hook fires. Manually hooking `et_register_updates_component`
     * ensures Divi's updater is active when we call `wp_update_themes()`.
     */
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
            // Silently fail — Divi update detection is best-effort.
        }
    }
}
