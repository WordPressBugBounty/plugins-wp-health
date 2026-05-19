<?php
namespace WPUmbrella\Actions;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;

class AutoInstallByConstant implements ExecuteHooks, ActivationHook
{
    const RETRY_LOCK = 'wp_umbrella_auto_install_lock';

    public function hooks()
    {
        add_action('init', [$this, 'handleAutoInstall']);
    }

    public function activate()
    {
        $this->handleAutoInstall();
    }

    public function handleAutoInstall()
    {
        if (!$this->shouldRun()) {
            return;
        }

        $optionService = wp_umbrella_get_service('Option');

        $optionService->setOptions([
            'allowed' => true,
            'api_key' => WP_UMBRELLA_API_KEY,
            'project_id' => '',
        ]);

        $paired = wp_umbrella_get_service('PairingService')->runPairing();

        if (!$paired) {
            set_transient(self::RETRY_LOCK, 1, HOUR_IN_SECONDS);
        }
    }

    protected function shouldRun()
    {
        if (!defined('WP_UMBRELLA_AUTO_INSTALL_WITH_CONSTANT') || !WP_UMBRELLA_AUTO_INSTALL_WITH_CONSTANT) {
            return false;
        }

        if (!defined('WP_UMBRELLA_API_KEY') || empty(WP_UMBRELLA_API_KEY)) {
            return false;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }

        if (get_transient(self::RETRY_LOCK)) {
            return false;
        }

        $optionService = wp_umbrella_get_service('Option');

        if (!empty($optionService->getRequestTokenWithoutCache())) {
            return false;
        }

        return empty($optionService->getApiKeyWithoutCache());
    }
}
