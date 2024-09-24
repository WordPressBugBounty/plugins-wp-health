<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;

class MoveBackupModule extends AbstractController
{
    public function executeGet($params)
    {
        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();

        $filename = sanitize_file_name($params['filename'] ?? null);
        $requestId = sanitize_text_field($params['requestId'] ?? null);

        if (empty($filename)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_filename',
            ]);
        }

        if (empty($requestId)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_request_id',
            ]);
        }

        // Initialize the WordPress Filesystem
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();

        global $wp_filesystem;

        $sourceFilePath = WP_UMBRELLA_DIR . DIRECTORY_SEPARATOR . 'request' . DIRECTORY_SEPARATOR . 'cloner.php';
        $destinationPath = $source . $filename;

        if (!file_exists($sourceFilePath)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'source_file_not_found',
            ]);
        }

        $result = $wp_filesystem->copy($sourceFilePath, $destinationPath, true, 0755);

        if (!$result) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'error',
            ]);
        }

        $fileContent = $wp_filesystem->get_contents($sourceFilePath);

        $fileContent = str_replace("define('UMBRELLA_BACKUP_KEY', '[[UMBRELLA_BACKUP_KEY]]');", "define('UMBRELLA_BACKUP_KEY', '" . $requestId . "');", $fileContent);
        $fileContent = str_replace("define('UMBRELLA_DB_HOST', '[[UMBRELLA_DB_HOST]]');", "define('UMBRELLA_DB_HOST', '" . DB_HOST . "');", $fileContent);
        $fileContent = str_replace("define('UMBRELLA_DB_NAME', '[[UMBRELLA_DB_NAME]]');", "define('UMBRELLA_DB_NAME', '" . DB_NAME . "');", $fileContent);
        $fileContent = str_replace("define('UMBRELLA_DB_USER', '[[UMBRELLA_DB_USER]]');", "define('UMBRELLA_DB_USER', '" . DB_USER . "');", $fileContent);
        $fileContent = str_replace("define('UMBRELLA_DB_SSL', '[[UMBRELLA_DB_SSL]]');", "define('UMBRELLA_DB_SSL', " . (defined('DB_SSL') ? 'true' : 'false') . ');', $fileContent);

        $password = DB_PASSWORD;
        if (strpos($password, "'") !== false) {
            // Note: the quotes are part of the string
            $fileContent = str_replace(
                "define('UMBRELLA_DB_PASSWORD', '[[UMBRELLA_DB_PASSWORD]]');",
                'define("UMBRELLA_DB_PASSWORD", "' . $password . '");',
                $fileContent
            );
        } else {
            $fileContent = str_replace(
                "define('UMBRELLA_DB_PASSWORD', '[[UMBRELLA_DB_PASSWORD]]');",
                "define('UMBRELLA_DB_PASSWORD', '" . $password . "');",
                $fileContent
            );
        }

        if (defined('WPE_APIKEY')) {
            $str = "define('WPE_APIKEY', '" . WPE_APIKEY . "');";
            $fileContent = str_replace('//[[REPLACE]]//', $str, $fileContent);
        }

        $wp_filesystem->put_contents($destinationPath, $fileContent);

        return $this->returnResponse([
            'success' => true,
            'code' => 'success',
        ]);
    }
}
