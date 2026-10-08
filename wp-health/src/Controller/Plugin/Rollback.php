<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Services\Plugin\UpdateLock;

if (!defined('ABSPATH')) {
    exit;
}

class Rollback extends AbstractController
{
    public function executePost($params)
    {
        $plugin = isset($params['plugin']) ? $params['plugin'] : null;
        $version = isset($params['version']) ? $params['version'] : null;
        $lockToken = isset($params['lock_token']) ? $params['lock_token'] : null;

        if (!$plugin || !$version) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        if (!defined('WP_UMBRELLA_PROCESS_FROM_UMBRELLA')) {
            define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);
        }

        $pluginSlug = dirname($plugin);
        $hasToken = is_string($lockToken) && $lockToken !== '';
        $lock = wp_umbrella_get_service('UpdateLock');

        if ($lock->isHeldByOther($pluginSlug, $lockToken)) {
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress',
                'message' => sprintf('An update is already in progress for plugin %s', $pluginSlug),
                'holder' => $lock->holderFingerprint($pluginSlug),
                'data' => '',
            ]);
        }

        if ($lock->phase($pluginSlug) === UpdateLock::PHASE_UPDATING) {
            return $this->returnResponse([
                'status' => 'in_progress',
                'code' => 'update_in_progress_self',
                'message' => sprintf('An update is still running for plugin %s', $pluginSlug),
                'holder' => $lock->holderFingerprint($pluginSlug),
                'data' => '',
            ]);
        }

        $managePlugin = wp_umbrella_get_service('ManagePlugin');
        $markedUpdating = $hasToken && $lock->markPhase($pluginSlug, $lockToken, UpdateLock::PHASE_UPDATING);

        try {
            $data = $managePlugin->rollback($plugin, [
                'version' => $version,
            ]);

            if (isset($data['status']) && $data['status'] === 'success' && $hasToken) {
                $lock->release($pluginSlug, $lockToken);
            } elseif ($markedUpdating) {
                $lock->finishIfUpdating($pluginSlug, $lockToken);
            }

            return $this->returnResponse($data);
        } catch (\Throwable $e) {
            if ($markedUpdating) {
                $lock->finishIfUpdating($pluginSlug, $lockToken);
            }

            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
