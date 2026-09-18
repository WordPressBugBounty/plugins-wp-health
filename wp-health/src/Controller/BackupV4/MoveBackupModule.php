<?php
namespace WPUmbrella\Controller\BackupV4;

use WPUmbrella\Core\BackupScript\BackupScriptCompiler;
use WPUmbrella\Core\BackupScript\BackupScriptManifest;
use WPUmbrella\Core\Models\AbstractController;
use WPUmbrella\Helpers\Opcache;

class MoveBackupModule extends AbstractController
{
    /**
     * The file list and its order come from the manifest, the same one the
     * developer entry point reads. They used to be two arrays kept in two
     * files, which had already drifted: the same sources compiled into two
     * different scripts depending on which path deployed them.
     *
     * @param string $generation
     * @param string $outputFilePath
     */
    protected function mergeFiles($generation, $outputFilePath)
    {
        $compiler = new BackupScriptCompiler(WP_UMBRELLA_DIR);

        global $wp_filesystem;

        $wp_filesystem->put_contents($outputFilePath, $compiler->compile($generation), 0755);
    }

    /**
     * @param string $value
     * @return string
     */
    protected function escapeForSingleQuotedString($value)
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value);
    }

    protected function getWriteDiagnostics($destinationPath)
    {
        $directory = dirname($destinationPath);
        $probePath = $directory . DIRECTORY_SEPARATOR . 'umbrella-write-probe.tmp';

        $lastError = error_get_last();

        $nativeWrite = @file_put_contents($probePath, 'probe') !== false;

        if ($nativeWrite) {
            @unlink($probePath);
        }

        return [
            'filesystem_method' => function_exists('get_filesystem_method') ? get_filesystem_method() : null,
            'native_write' => $nativeWrite,
            'directory_writable' => @is_writable($directory),
            'php_error' => isset($lastError['message']) ? substr($lastError['message'], 0, 200) : null,
        ];
    }

    public function executePost($params)
    {
        return $this->executeGet($params);
    }

    public function executeGet($params)
    {
        $generation = BackupScriptManifest::GENERATION_BACKUP_V4;
        $source = wp_umbrella_get_service('BackupFinderConfiguration')->getRootBackupModule();
        $filename = BackupScriptManifest::generation($generation)['output'];
        $requestId = sanitize_text_field($params['requestId'] ?? null);

        if (empty($requestId)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'no_request_id',
            ]);
        }

        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $requestId)) {
            return $this->returnResponse([
                'success' => false,
                'code' => 'invalid_request_id',
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

            $this->mergeFiles($generation, $destinationPath);

            $fileContent = $wp_filesystem->get_contents($destinationPath);

            $dbHost = wp_umbrella_get_service('WordPressContext')->getDbHost();

            $fileContent = str_replace("define('UMBRELLA_BACKUP_KEY', '[[UMBRELLA_BACKUP_KEY]]');", "define('UMBRELLA_BACKUP_KEY', '" . $requestId . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DEPLOYED_AT', '[[UMBRELLA_DEPLOYED_AT]]');", "define('UMBRELLA_DEPLOYED_AT', " . time() . ');', $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_HOST', '[[UMBRELLA_DB_HOST]]');", "define('UMBRELLA_DB_HOST', '" . $this->escapeForSingleQuotedString($dbHost) . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_NAME', '[[UMBRELLA_DB_NAME]]');", "define('UMBRELLA_DB_NAME', '" . $this->escapeForSingleQuotedString(DB_NAME) . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_USER', '[[UMBRELLA_DB_USER]]');", "define('UMBRELLA_DB_USER', '" . $this->escapeForSingleQuotedString(DB_USER) . "');", $fileContent);
            $fileContent = str_replace("define('UMBRELLA_DB_SSL', '[[UMBRELLA_DB_SSL]]');", "define('UMBRELLA_DB_SSL', " . (defined('DB_SSL') ? 'true' : 'false') . ');', $fileContent);

            $fileContent = str_replace(
                "define('UMBRELLA_DB_PASSWORD', '[[UMBRELLA_DB_PASSWORD]]');",
                "define('UMBRELLA_DB_PASSWORD', '" . $this->escapeForSingleQuotedString(DB_PASSWORD) . "');",
                $fileContent
            );

            if (defined('WPE_APIKEY')) {
                $str = "if(!defined('WPE_APIKEY')){define('WPE_APIKEY', '" . WPE_APIKEY . "');}";
                $fileContent = str_replace('//[[REPLACE]]//', $str, $fileContent);
            }

            $result = $wp_filesystem->put_contents($destinationPath, $fileContent);

            if (!Opcache::invalidate($destinationPath)) {
                Opcache::reset();
            }

            if (!$result) {
                return $this->returnResponse([
                    'success' => false,
                    'code' => 'write_error',
                    'diagnostics' => $this->getWriteDiagnostics($destinationPath),
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
            'module_url' => untrailingslashit(site_url()) . '/' . $filename,
        ]);
    }
}
