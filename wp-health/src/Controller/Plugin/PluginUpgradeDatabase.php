<?php
namespace WPUmbrella\Controller\Plugin;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class PluginUpgradeDatabase extends AbstractController
{
    public function executePost($params)
    {
        $slugPlugin = isset($params['plugin']) ? $params['plugin'] : null;

        if (!$slugPlugin) {
            return $this->returnResponse(['code' => 'missing_parameters', 'message' => 'No plugin'], 400);
        }

        define('WP_UMBRELLA_PROCESS_FROM_UMBRELLA', true);

        try {
            switch($slugPlugin) {
                case 'woocommerce/woocommerce.php':
                    wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
                    break;
                case 'elementor/elementor.php':
                case 'elementor-pro/elementor-pro.php':
                    wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
                    break;
                default:
                    do_action('wp_umbrella_plugin_upgrade_database', $slugPlugin);
                    break;
            }

            return $this->returnResponse([
                'code' => 'success',
            ]);
        } catch (\Exception $e) {
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
