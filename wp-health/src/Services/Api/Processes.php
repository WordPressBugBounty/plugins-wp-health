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
        try {
            $response = wp_umbrella_handle_outbound_response(wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/external/processes', [
                'method' => 'POST',
                'body' => json_encode($data),
                'headers' => $this->getHeadersV2(),
                'sslverify' => wp_umbrella_should_verify_ssl(),
                'timeout' => 10,
            ]));
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }
}
