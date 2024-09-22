<?php
namespace WPUmbrella\Actions\Admin;

use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooksBackend;

class PrepareMuPlugins implements ExecuteHooksBackend, DeactivationHook
{
    public function hooks()
    {
        if (file_exists(WPMU_PLUGIN_DIR . '/InitUmbrella.php')) {
            return;
        }

        $result = $this->createHandler();
    }

    protected function createHandler()
    {
        if (!is_writable(dirname(WPMU_PLUGIN_DIR))) {
            return false;
        }

        if (!file_exists(WPMU_PLUGIN_DIR)) {
            wp_mkdir_p(WPMU_PLUGIN_DIR);
        }

        try {
            if (!@copy(
                WP_UMBRELLA_DIR . '/src/Core/MuPlugins/InitUmbrella.php',
                WPMU_PLUGIN_DIR . '/InitUmbrella.php'
            )) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function deactivate()
    {
        if (!file_exists(WPMU_PLUGIN_DIR . '/InitUmbrella.php')) {
            return;
        }

        if (!is_writable(WPMU_PLUGIN_DIR . '/InitUmbrella.php')) {
            return;
        }

        @unlink(WPMU_PLUGIN_DIR . '/InitUmbrella.php');
    }
}
