<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;


class WordPressSize extends AbstractController
{
    public function executeGet($params)
    {
        $data = wp_umbrella_get_service('WordPressDataProvider')->getSizes();

        return $this->returnResponse($data);
    }
}
