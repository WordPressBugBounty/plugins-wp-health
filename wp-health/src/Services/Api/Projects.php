<?php
namespace WPUmbrella\Services\Api;

class Projects extends BaseClient
{
    /**
     * @params $data [
     * 		base_url: string,
     * 		rest_url: string
     *      secret_token: string,
     * ]
     * @return array
     */
    public function validateSecretToken($data, $token = null)
    {
        add_filter('https_ssl_verify', '__return_false');
        try {
            $response = wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/projects/validation-secret-token', [
                'headers' => $this->getHeadersV2($token),
                'body' => json_encode($data),
                'sslverify' => false,
                'timeout' => 50,
            ]);
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }

    /**
     * @return array
     */
    public function createProjectOnApplication($data, $token = null)
    {
        add_filter('https_ssl_verify', '__return_false');
        try {
            $response = wp_remote_post(WP_UMBRELLA_NEW_API_URL . '/v1/external/projects', [
                'headers' => $this->getHeadersV2($token),
                'body' => json_encode($data),
                'sslverify' => false,
                'timeout' => 50,
            ]);
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }

    public function snapshotData($data, $token = null)
    {
        try {
            $id = wp_umbrella_get_option('project_id');
            if (!$id) {
                return;
            }

            $url = sprintf(WP_UMBRELLA_NEW_API_URL . '/v1/projects/%s/snapshot-wp-data', $id);
            $response = wp_remote_post($url, [
                'headers' => $this->getHeadersV2($token),
                'body' => json_encode($data),
                'timeout' => 50,
            ]);

            if (is_wp_error($response)) {
                $url = sprintf(WP_UMBRELLA_API_URL . '/v1/projects/%s/snapshot-wp-data', $id);
                $response = wp_remote_post($url, [
                    'headers' => $this->getHeadersV2($token),
                    'body' => json_encode($data),
                    'timeout' => 50,
                ]);
            }
        } catch (\Exception $e) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }
}
