<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class WhiteLabel extends AbstractController
{
    public function executePost($params)
    {

		delete_transient('wp_umbrella_white_label_data_cache');

        return $this->returnResponse(['success' => true]);
    }
}
