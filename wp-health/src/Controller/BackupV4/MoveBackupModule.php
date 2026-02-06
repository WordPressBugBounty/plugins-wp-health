<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\Models\AbstractController;

class MoveBackupModule extends AbstractController
{
    protected function mergeFiles($directory, $outputFilePath)
    {
        $outputContent = '';
        $firstFile = true;

        $fileOrder = [
            'DefaultException.php',
            'UmbrellaException.php',
            'UmbrellaInternalRequestException.php',
            'UmbrellaSocketException.php',
            'ProcessCapacityTrait.php',
            'UmbrellaPreventMaxExecutionTime.php',
            'UmbrellaDatabasePreventMaxExecutionTime.php',
            'ConnectionInterface.php',
            'DatabaseStatementInterface.php',
            'ChecksumDictionaryGenerator.php',
            'SiteChecksumDirectoryGenerator.php',
            'AbstractProcessBackup.php',
            'Context.php',
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        $sortedFiles = [];

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            if (strtolower($file->getExtension()) == 'php') {
                $sortedFiles[$file->getBasename()] = $file->getPathname();
            }
        }

        usort($sortedFiles, function ($a, $b) use ($fileOrder) {
            $isScriptA = basename($a) == 'script.php';
            $isScriptB = basename($b) == 'script.php';

            if ($isScriptA && !$isScriptB) {
                return 1;
            } elseif (!$isScriptA && $isScriptB) {
                return -1;
            }

            $posA = array_search(basename($a), $fileOrder);
            $posB = array_search(basename($b), $fileOrder);

            return ($posA !== false ? $posA : PHP_INT_MAX) - ($posB !== false ? $posB : PHP_INT_MAX);
        });

        foreach ($sortedFiles as $filePath) {
            $handle = fopen($filePath, 'rb');

            if ($handle) {
                $buffer = [];
                while (!feof($handle)) {
                    $buffer[] = fgets($handle, 400);
                }
                fclose($handle);
                $buffer[0][0] = chr(hexdec('FF')); // set the first byte to 0xFF
            }

            array_shift($buffer);
            $content = implode('', $buffer);
            $outputContent .= $content;
        }

        global $wp_filesystem;

        $wp_filesystem->put_contents($outputFilePath, "<?php \n" . $outputContent, 0755);
    }

    public function executeGet($params)
    {
        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();
        $filename = 'cloner.php';
        $requestId = sanitize_text_field($params['requestId'] ?? null);

        if (empty($requestId)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_request_id',
            ]);
        }

        try {
            // Initialize the WordPress Filesystem
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;

            $destinationPath = $source . $filename;

            if (file_exists($destinationPath)) {
                $wp_filesystem->delete($destinationPath);
            }

            $this->mergeFiles(WP_UMBRELLA_DIR . DIRECTORY_SEPARATOR . 'backup-script', $destinationPath);

            $fileContent = $wp_filesystem->get_contents($destinationPath);

            $dbHost = wp_umbrella_get_service('WordPressContext')->getDbHost();

            $fileContent = str_replace("define('UMBRELLA_BACKUP_KEY', '[[UMBRELLA_BACKUP_KEY]]');", "define('UMBRELLA_BACKUP_KEY', '" . $requestId . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_HOST', '[[UMBRELLA_DB_HOST]]');", "define('UMBRELLA_DB_HOST', '" . $dbHost . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_NAME', '[[UMBRELLA_DB_NAME]]');", "define('UMBRELLA_DB_NAME', '" . DB_NAME . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_USER', '[[UMBRELLA_DB_USER]]');", "define('UMBRELLA_DB_USER', '" . DB_USER . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_SSL', '[[UMBRELLA_DB_SSL]]');", "define('UMBRELLA_DB_SSL', " . (defined('DB_SSL') ? 'true' : 'false') . ');', $fileContent);

            $password = DB_PASSWORD;
            // Escape backslashes first, then single quotes (for single-quoted PHP strings)
            $escapedPassword = str_replace(['\\', "'"], ['\\\\', "\\'"], $password);
            $fileContent = str_replace(
                "define('UMBRELLA_DB_PASSWORD', '[[UMBRELLA_DB_PASSWORD]]');",
                "define('UMBRELLA_DB_PASSWORD', '" . $escapedPassword . "');",
                $fileContent
            );

            if (defined('WPE_APIKEY')) {
                $str = "define('WPE_APIKEY', '" . WPE_APIKEY . "');";
                $fileContent = str_replace('//[[REPLACE]]//', $str, $fileContent);
            }

            $result = $wp_filesystem->put_contents($destinationPath, $fileContent);

            if (function_exists('opcache_invalidate')) {
                $resultOpcache = @opcache_invalidate($destinationPath, true);
                if (!$resultOpcache) {
                    if (function_exists('opcache_reset')) {
                        @opcache_reset();
                    }
                }
            }

            if (!$result) {
                return $this->returnResponse([
                    'success' => false,
                    'code' => 'write_error',
                ]);
            }
        } catch (\Exception $e) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        return $this->returnResponse([
            'success' => true,
            'code' => 'success',
        ]);
    }
}
