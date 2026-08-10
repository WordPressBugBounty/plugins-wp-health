<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;

class UploadModule extends AbstractController
{
    const ALLOWED_FILENAMES = ['cloner.php', 'restore.php'];

    protected function withFile()
    {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'upload_error',
            ]);
        }

        $filename = sanitize_file_name(basename($_FILES['file']['name']));

        if (!in_array($filename, self::ALLOWED_FILENAMES, true)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'invalid_filename',
            ]);
        }

        $destination = ABSPATH . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            return $this->returnResponse([
                'success' => true,
                'code' => 'upload_success'
            ]);
        }

        return $this->returnResponse([
            'success' => false,
            'code' => 'upload_error'
        ]);
    }

    public function executePost($params)
    {
        if (!empty($_FILES) && isset($_FILES['file'])) {
            return $this->withFile();
        }

        if (!isset($params['file']) || !isset($params['filename'])) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_file',
            ]);
        }

        $filename = sanitize_file_name(basename((string) $params['filename']));

        if (!in_array($filename, self::ALLOWED_FILENAMES, true)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'invalid_filename',
            ]);
        }

        $str = base64_decode($params['file']);

        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();

        $result = file_put_contents($source . $filename, $str);

        if (function_exists('opcache_invalidate')) {
            $result = opcache_invalidate($source . $filename, true);
            if (!$result) {
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
            }
        }

        return $this->returnResponse([
            'success' => $result === false ? false : true,
            'code' => $result === false ? 'upload_error' : 'upload_success',
        ]);
    }
}
