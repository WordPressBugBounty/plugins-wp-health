<?php
namespace WPUmbrella\Controller\Core;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class Update extends AbstractController
{
    public function executePost($params)
    {
        try {
            $type = $params['type'] ?? 'classic';
            $version = isset($params['version']) ? $params['version'] : null;

            // A malformed target must not fall back to the latest release. A number is refused too: 6.10 would reach us as 6.1.
            if ($version !== null && (!is_string($version) || $version === '')) {
                return $this->returnResponse([
                    'status' => 'error',
                    'code' => 'invalid_version',
                ], 400);
            }

            define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

            // WP_Automatic_Updater picks its own offer, only Core_Upgrader can be pointed at a version.
            if ($type === 'core_upgrader' || $version !== null) {
                $data = wp_umbrella_get_service('CoreUpdate')->upgradeByCoreUpgrader($version);
            } else {
                $data = wp_umbrella_get_service('CoreUpdate')->update();
            }

            if (!isset($data['status']) || $data['status'] === 'error') {
                return $this->returnResponse($data, 403);
            }

            return $this->returnResponse($data);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ], 403);
        }
    }
}
