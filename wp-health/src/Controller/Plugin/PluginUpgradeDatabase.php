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

        $trace = wp_umbrella_get_service('RequestTrace');

        try {
            $trace->addTrace('upgrade_database_started', ['plugin' => $slugPlugin]);

            switch($slugPlugin) {
                case 'woocommerce/woocommerce.php':
                    wp_umbrella_get_service('WooCommerceDatabase')->updateDatabase();
                    $trace->addTrace('woocommerce_database_upgraded');
                    break;
                case 'elementor/elementor.php':
                case 'elementor-pro/elementor-pro.php':
                    wp_umbrella_get_service('ElementorDatabase')->updateDatabase();
                    $trace->addTrace('elementor_database_upgraded');
                    break;
                default:
                    do_action('wp_umbrella_plugin_upgrade_database', $slugPlugin);
                    $trace->addTrace('generic_database_upgraded');
                    break;
            }

            return $this->returnResponse([
                'code' => 'success',
            ]);
        } catch (\Exception $e) {
            $trace->addTrace('upgrade_database_exception', ['message' => $e->getMessage()]);
            return $this->returnResponse([
                'code' => 'unknown_error',
                'messsage' => $e->getMessage()
            ]);
        }
    }
}
