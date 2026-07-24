<?php
namespace WPUmbrella\Actions\Hardening;

use WPUmbrella\Core\Hooks\ActivationHook;
use WPUmbrella\Core\Hooks\DeactivationHook;
use WPUmbrella\Core\Hooks\ExecuteHooks;

if (!defined('ABSPATH')) {
    exit;
}

class HtaccessLifecycle implements ActivationHook, DeactivationHook, ExecuteHooks
{
    const PENDING_WRITE_OPTION = 'wp_umbrella_htaccess_pending_write';

    public function hooks()
    {
        add_action('init', [$this, 'maybeCompletePendingWrite']);
    }

    public function activate()
    {
        if (!wp_umbrella_get_service('HardeningSettings')->isEnabled('htaccess_umbrella_block')) {
            return;
        }

        if (wp_umbrella_get_service('HtaccessFile')->hasUmbrellaBlock()) {
            return;
        }

        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            update_option(self::PENDING_WRITE_OPTION, 1, false);
            return;
        }

        $this->writeOrDisable();
    }

    public function maybeCompletePendingWrite()
    {
        if (!get_option(self::PENDING_WRITE_OPTION)) {
            return;
        }

        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            return;
        }

        delete_option(self::PENDING_WRITE_OPTION);

        $this->writeOrDisable();
    }

    public function deactivate()
    {
        wp_umbrella_get_service('HtaccessFile')->cleanUmbrellaBlock();
        delete_option(self::PENDING_WRITE_OPTION);
    }

    protected function writeOrDisable()
    {
        $settings = wp_umbrella_get_service('HardeningSettings');

        if (!$settings->isEnabled('htaccess_umbrella_block')) {
            return;
        }

        $htaccess = wp_umbrella_get_service('HtaccessFile');

        if ($htaccess->hasUmbrellaBlock()) {
            return;
        }

        $result = $htaccess->writeUmbrellaBlock();

        if (!isset($result['status']) || $result['status'] !== 'ok') {
            $settings->updateSettings(['htaccess_umbrella_block' => false]);
        }
    }
}
