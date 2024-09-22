<?php
namespace WPUmbrella\Controller\Restore\V2;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Core\Restore\Memento\RestoreOriginator;
use WPUmbrella\Core\Restore\Builder\RestoreBuilder;

class GetData extends AbstractController
{
    public function executeGet($params)
    {
		try {
			$files = wp_umbrella_get_service('RestoreWordPressData')->getWordPressFiles();
			$database = wp_umbrella_get_service('RestoreWordPressData')->getWordPressDatabase();

			if($files['abspath'] === null){
				return $this->response->json([
					'success' =>  false,
					'data' => [
						'code' => 'error_files',
						'message' => 'Error getting WordPress files',
					]
				],400);
			}

			if($database['user'] === null){
				return $this->response->json([
					'success' => false,
					'data' => [
						'code' => 'error_database',
						'message' => 'Error getting WordPress database',
					]
				],400);
			}

			return $this->returnResponse([
				'success' => true,
				'data' => [
					'files' => $files,
					'database' => $database
				]
			], 200);
		} catch (\Exception $e) {
			return $this->returnResponse([
				'success' => false,
				'data' => [
					'code' => 'error',
					'message' => $e->getMessage(),
				]
			], 500);
		}
    }
}
