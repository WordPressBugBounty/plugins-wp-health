<?php
namespace WPUmbrella\Services\Api;

use WPUmbrella\Models\Backup\BackupProcessedData;

class Backup extends BaseClient
{
    const NAME_SERVICE = 'BackupApi';

    /**
    * Upload directly on the cloud storage

    * @param string $signedUrl
    * @param string $filename
    * @return void
    */
    public function postBackupBySignedUrl($signedUrl, $filename)
    {
        $directorySuffix = get_option('wp_umbrella_backup_suffix_security');
        $filePath = @realpath(sprintf('%s/%s/%s', WP_UMBRELLA_DIR_WPU_BACKUP_BOX, $directorySuffix, $filename));

        if (!file_exists($filePath)) {
            return ['success' => false];
        }

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_PUT, 1);
            curl_setopt($curl, CURLOPT_INFILESIZE, filesize($filePath));
            curl_setopt($curl, CURLOPT_INFILE, ($in = fopen($filePath, 'r')));
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/zip']);
            curl_setopt($curl, CURLOPT_URL, $signedUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($curl);
            if (!empty($response)) {
                return;
            }
        } catch (\Exception $e) {
        }
    }

    /**
     *
     * @param string $filename
     * @return string
     */
    public function getSignedUrlForUpload($filename)
    {
        if (!$this->canRequestApi()) {
            return null;
        }

        $projectId = wp_umbrella_get_option('project_id');
        $url = sprintf('%s/v1/projects/%s/backups/signed-url?filename=%s', WP_UMBRELLA_NEW_API_URL, $projectId, $filename);

        try {
            add_filter('https_ssl_verify', '__return_false');
            $response = wp_remote_get($url, [
                'headers' => $this->getHeadersV2(),
                'sslverify' => false,
                'timeout' => 50,
            ]);
        } catch (\Exception $e) {
            return null;
        }

        try {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($body['success']) || !$body['success']) {
                return null;
            }

            return isset($body['result']['signed_url']) ? $body['result']['signed_url'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function postInitBackup($data)
    {
        return null;
    }

    public function putUpdateBackupData($data)
    {
        return null;
    }

    public function postErrorBackup($data)
    {
        return null;
    }

    public function postFinishBackup($data)
    {
        return null;
    }
}
