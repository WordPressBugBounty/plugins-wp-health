<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Helpers\Opcache;

class UploadModule extends AbstractController
{
    const ALLOWED_FILENAMES = ['cloner.php', 'restore.php'];

    public function executePost($params)
    {
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

        if (!Opcache::invalidate($source . $filename)) {
            Opcache::reset();
        }

        return $this->returnResponse([
            'success' => $result === false ? false : true,
            'code' => $result === false ? 'upload_error' : 'upload_success',
        ]);
    }
}
