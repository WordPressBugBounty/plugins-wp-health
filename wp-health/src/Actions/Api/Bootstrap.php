<?php
namespace WPUmbrella\Actions\Api;

use WPUmbrella\Core\Kernel;
use WPUmbrella\Core\Hooks\ExecuteHooks;
use WPUmbrella\Helpers\Controller;
use WPUmbrella\Core\Controllers;

class Bootstrap implements ExecuteHooks
{
    public function hooks()
    {
        add_action('rest_api_init', [$this, 'register']);
    }

    public function register()
    {
        $controllers = Controllers::getControllers();

        foreach ($controllers as $key => $item) {
            if (!isset($item['route']) || empty($item['route'])) {
                continue;
            }

            foreach ($item['methods'] as $key => $data) {
                // A controller file emptied or truncated on disk must cost its own
                // route, not every REST request the site serves.
                if (!class_exists($data['class'])) {
                    continue;
                }

                $options = isset($data['options']) ? $data['options'] : [];
                $options['from'] = Controller::API;
                $options['route'] = $item['route'];
                $options['method'] = $data['method'];
                $options['version'] = isset($item['version']) ? $item['version'] : 'v1';

                $controller = new $data['class']($options);

                $controller->execute();
            }
        }
    }
}
