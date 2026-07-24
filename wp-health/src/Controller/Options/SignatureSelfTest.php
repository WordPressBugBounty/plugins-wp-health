<?php
namespace WPUmbrella\Controller\Options;

use WPUmbrella\Core\Models\AbstractController;

if (!defined('ABSPATH')) {
    exit;
}

class SignatureSelfTest extends AbstractController
{
    public function executePost($params)
    {
        return $this->returnResponse([
            'success' => true,
            'code' => 'success',
        ]);
    }

    public function executeGet($params)
    {
        return $this->executePost($params);
    }
}
