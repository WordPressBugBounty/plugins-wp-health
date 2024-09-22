<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class MigrateApiKey extends AbstractController
{
    public function executePost($params)
    {
        $options = wp_umbrella_get_options([
            'secure' => false
        ]);

        $options['api_key'] = $params['api_key'];
        wp_umbrella_get_service('Option')->setOptions($options);

        return $this->returnResponse([
            'code' => 'success'
        ]);
    }
}
