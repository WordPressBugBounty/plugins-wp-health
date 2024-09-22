<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;


class UmbrellaNonceLogin extends AbstractController
{
    public function executePost($params)
    {
		$hash = md5((new \DateTime())->format('Y-m-d H:i:s'));
        update_option('wp_umbrella_login', $hash, false);

        return $this->returnResponse([
            'nonce' => $hash
        ]);
    }
}
