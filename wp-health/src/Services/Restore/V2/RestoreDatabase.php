<?php
namespace WPUmbrella\Services\Restore\V2;

use Coderatio\SimpleBackup\SimpleBackup;
use Exception;

class RestoreDatabase
{
    public function handle(string $filename): array
    {
        if (!file_exists($filename)) {
            return [
                // Success true because no need to prevent this.
                'success' => true,
                'data' => [],
            ];
        }

        try {
            $host = wp_umbrella_get_service('WordPressContext')->getDbHost();
            // Prevent DB_HOST with port
            if (
                apply_filters('wp_umbrella_explode_host', true) &&
                (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ARGOS') !== false) &&
                strpos($host, ':') !== false) {
                $host = explode(':', $host)[0];
            }

            $bySock = apply_filters('wp_umbrella_connect_by_sock', false);
            $sockValue = null;
            if ($bySock) {
                $sockValue = apply_filters('wp_umbrella_connect_by_sock_host', '');
                if (strpos(DB_HOST, '.sock') !== false && empty($sockValue)) {
                    // eg: localhost:/var/run/mysqld/mysqld.sock
                    $dataSockExplode = explode(':', DB_HOST);
                    $sockValue = isset($dataSockExplode[1]) ? $dataSockExplode[1] : '';
                }
            }

            ob_start();
            if (!$bySock && !empty($sockValue)) {
                $simpleBackup = SimpleBackup::setDatabase([
                    DB_NAME,
                    DB_USER,
                    DB_PASSWORD,
                    $host,
                ]);
            } else {
                $simpleBackup = SimpleBackup::setDatabase([
                    DB_NAME,
                    DB_USER,
                    DB_PASSWORD,
                    $host,
                    $sockValue
                ]);
                $simpleBackup->setDbHostSock($sockValue);
            }

            $simpleBackup->importFrom($filename);

            $output = ob_get_clean();
            if (!empty($output)) {
                return [
                    'success' => false,
                    'data' => [
                        'code' => 'restore_failed_database',
                        'message' => $output,
                    ],
                ];
            }

            if (file_exists($filename)) {
                @unlink($filename);
            }

            return [
                'success' => true,
                'data' => [
                    'message' => sprintf('Table %s restored', basename($filename)),
                    'output' => $output
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => [
                    'code' => 'restore_failed_database',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
