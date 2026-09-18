<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class KillLegacyCommunication extends AbstractController
{
    public function executePost($params)
    {
        return $this->cutover();
    }

    public function executeGet($params)
    {
        return $this->cutover();
    }

    protected function cutover()
    {
        $option = wp_umbrella_get_service('Option');

        $options = $option->getOptions(['secure' => false]);
        $options['key_state'] = 'new';
        $options['secret_token'] = '';
        $option->setOptions($options);

        wp_load_alloptions(true);

        $stored = $option->getOptions(['secure' => false]);

        return $this->returnResponse([
            'code' => 'success',
            'key_state' => 'new',
            'secret_token_cleared' => empty($stored['secret_token']),
        ]);
    }
}
