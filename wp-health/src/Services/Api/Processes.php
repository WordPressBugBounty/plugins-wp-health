<?php
namespace WPUmbrella\Services\Api;

class Processes extends BaseClient
{
    /**
     * @params $data [
     *  "type" => "plugin",
     *  "action" => "update",
     *  "values =>[
     * 		"name" => string,
     * 		"old_version" => string,
     * 		"version" => string,
     * 		"plugin" => string,
     *   ]
     * ] |
     * [
     * 	 "type" => "theme"
     *   "action" => "update",
     *   "values => [
     * 			"name" => string,
     * 			"old_version" => string,
     * 			"version" => string,
     * 			"theme" => string,
     * 		]
     * ] |
     * 	[
     * 	   "type" => "core"
     *     "action" => "update",
     * 	   "values => [
     *        "old_version" => string,
     * 		  "version" => string,
     *     ]
     * ]
     *
     * @return array
     */
    public function addProcessTask($data)
    {
        add_filter('https_ssl_verify', '__return_false');
        try {
            $response = wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/external/processes', [
                'method' => 'POST',
                'body' => json_encode($data),
                'headers' => $this->getHeadersV2(),
                'timeout' => 10,
            ]);
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }
}
