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
        wp_umbrella_set_key_state('new');

        return $this->returnResponse([
            'code' => 'success',
            'key_state' => 'new',
        ]);
    }
}
