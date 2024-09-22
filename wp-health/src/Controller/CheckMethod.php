<?php
namespace WPUmbrella\Controller;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\UmbrellaRequest;


class CheckMethod extends AbstractController
{

	protected function getCheckMethod(){

		$request = new UmbrellaRequest();
		$method = $request->getMethod();

        return $this->returnResponse([
            'code' => 'success',
			'data' => [
				'method' => $method
			]
        ]);
	}

    public function executePost($params)
    {
		return $this->getCheckMethod();
    }

    public function executeGet($params)
    {
    	return $this->getCheckMethod();
    }
}
