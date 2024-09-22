<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;


class WordPressInfo extends AbstractController
{
    public function executeGet($params)
    {
        $data = wp_umbrella_get_service('WordPressProvider')->get();

        return $this->returnResponse($data);
    }
}
