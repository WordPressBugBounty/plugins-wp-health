<?php
namespace WPUmbrella\Services\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Models\Backup\BackupProcessedData;

class Restoration extends BaseClient
{
    const NAME_SERVICE = 'RestorationApi';



	/**
	 * @param int $restorationId
	 */
    public function postFinishRestoreFiles($restorationId)
    {
        if(!$this->canRequestApi()){
			return null;
		}

		$projectId = wp_umbrella_get_option('project_id');


        try {

            $url = sprintf(WP_UMBRELLA_API_URL . '/v1/projects/%s/restorations/%s/finish-files', $projectId, $restorationId);
			add_filter('https_ssl_verify', '__return_false');
            $response = wp_remote_post($url, [
                'headers' => $this->getHeadersV2(),
                'timeout' => 55,
            ]);

            return $response;
        } catch (\Exception $e) {
            \wp_umbrella_get_service('Logger')->error($e->getMessage());
            return null;
        }
    }

	/**
	 * @param int $restorationId
	 */
    public function postFinishRestoreDatabase($restorationId)
    {
        if(!$this->canRequestApi()){
			return null;
		}

		$projectId = wp_umbrella_get_option('project_id');

        try {

            $url = sprintf(WP_UMBRELLA_API_URL . '/v1/projects/%s/restorations/%s/finish-database', $projectId, $restorationId);
			add_filter('https_ssl_verify', '__return_false');
            $response = wp_remote_post($url, [
                'headers' => $this->getHeadersV2(),
                'timeout' => 55,
            ]);

            return $response;
        } catch (\Exception $e) {
            \wp_umbrella_get_service('Logger')->error($e->getMessage());
            return null;
        }
    }

	/**
	 * @param int $restorationId
	 * @param array $data
	 *  [
	 * 		"type" => files | database
	 * 	]
	 */
    public function postFinishDownloadZip($restorationId, $data)
    {
        if(!$this->canRequestApi()){
			return null;
		}

		$projectId = wp_umbrella_get_option('project_id');

        try {

            $url = sprintf(WP_UMBRELLA_API_URL . '/v1/projects/%s/restorations/%s/finish-download', $projectId, $restorationId);

            $response = wp_remote_post($url, [
                'headers' => $this->getHeadersV2(),
                'body' => json_encode($data),
                'timeout' => 55,
            ]);

            return $response;
        } catch (\Exception $e) {
            \wp_umbrella_get_service('Logger')->error($e->getMessage());
            return null;
        }
    }

	/**
	 * @param int $restorationId
	 * @param array $data
	 *  [
	 * 		"config" => {}
	 * 		"logs" => {},
	 * 	]
	 */
    public function postLog($restorationId, $data )
    {
        if(!$this->canRequestApi()){
			return null;
		}

		$projectId = wp_umbrella_get_option('project_id');

        try {

            $url = sprintf(WP_UMBRELLA_API_URL . '/v1/projects/%s/restorations/%s/logs', $projectId, $restorationId);

            $response = wp_remote_post($url, [
                'headers' => $this->getHeadersV2(),
                'body' => json_encode($data),
                'timeout' => 55,
            ]);

            return $response;
        } catch (\Exception $e) {
            \wp_umbrella_get_service('Logger')->error($e->getMessage());
            return null;
        }
    }

	/**
	 * @param int $restorationId
	 * @param array $data
	 *  [
	 * 		"error_code" => ''
	 * 		"error_message" => '',
	 * 	]
	 */
    public function postOnError($restorationId, $data)
    {
        if(!$this->canRequestApi()){
			return null;
		}

		$projectId = wp_umbrella_get_option('project_id');

        try {

            $url = sprintf(WP_UMBRELLA_API_URL . '/v1/projects/%s/restorations/%s/process-in-error', $projectId, $restorationId);

            $response = wp_remote_post($url, [
                'headers' => $this->getHeadersV2(),
				'body' => json_encode($data),
                'timeout' => 55,
            ]);

            return $response;
        } catch (\Exception $e) {
            \wp_umbrella_get_service('Logger')->error($e->getMessage());
            return null;
        }
    }
}
