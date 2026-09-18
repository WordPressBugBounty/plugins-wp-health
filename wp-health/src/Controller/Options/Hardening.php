<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Hardening extends AbstractController
{
    public function executeGet($params)
    {
        $service = wp_umbrella_get_service('HardeningSettings');

        return $this->returnResponse([
            'success' => true,
            'settings' => $service->getStates(),
            'web_server' => wp_umbrella_get_service('WebServer')->getType(),
            'two_factor' => $this->getTwoFactorState($service),
        ]);
    }

    public function executePost($params)
    {
        $service = wp_umbrella_get_service('HardeningSettings');
        $settings = $service->updateSettings($params);

        return $this->returnResponse([
            'success' => true,
            'settings' => $settings,
            'web_server' => wp_umbrella_get_service('WebServer')->getType(),
            'htaccess_result' => $service->getLastHtaccessResult(),
            'file_editor_locked' => $service->isFileEditorLocked(),
            'two_factor' => $this->getTwoFactorState($service),
        ]);
    }

    protected function getTwoFactorState($service)
    {
        $guard = $service->getTwoFactorGuard();

        return [
            'enforceable' => $guard->isEnforceable(),
            'blocking_reason' => $guard->getBlockingReason(),
            'conflicting_plugin' => $guard->getConflictingPlugin(),
        ];
    }
}
