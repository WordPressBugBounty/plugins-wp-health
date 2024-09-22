<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

class ValidateApplicationToken extends AbstractController
{
    public function executePost($params)
    {
        return $this->returnResponse(['success' => true]);
    }

    public function executeGet($params)
    {
        return $this->returnResponse(['success' => true]);
    }
}
