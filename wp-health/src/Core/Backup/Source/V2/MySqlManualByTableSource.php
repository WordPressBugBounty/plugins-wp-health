<?php
namespace WPUmbrella\Core\Backup\Source\V2;

use WPUmbrella\Models\Backup\BackupSource;
use WPUmbrella\Models\Backup\BackupProcessCommandLine;
use Coderatio\SimpleBackup\SimpleBackup;
use WPUmbrella\Helpers\DataTemporary;
use ZipArchive;

class MySqlManualByTableSource implements BackupSource, BackupProcessCommandLine
{
    const DEFAULT_USER = 'root';
    const DEFAULT_TIMEOUT = 900;

    protected $name;
    protected $database;
    protected $host;
    protected $user;
    protected $password;
    protected $timeout;
    protected $namer;
    protected $sock;

    /**
     * @param string      $name
     * @param string      $database
     * @param array       $databaseConnexion
     * @param int         $timeout
     */
    public function __construct($namer, $database, $databaseConnexion = [], $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->version = 'v1';
        $this->namer = $namer;
        $this->database = $database;
        $this->host = isset($databaseConnexion['host']) ? $databaseConnexion['host'] : self::DEFAULT_USER;
        $this->user = isset($databaseConnexion['user']) ? $databaseConnexion['user'] : null;
        $this->password = isset($databaseConnexion['password']) ? $databaseConnexion['password'] : null;
        $this->sock = isset($databaseConnexion['sock']) ? $databaseConnexion['sock'] : null;
        $this->timeout = $timeout;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function getCommandLine()
    {
        return null;
    }

    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($scratchDir)
    {
        if ($this->version === 'v1') {
            $dataObj = wp_umbrella_get_service('BackupManageProcess')->getBackupData();
        } elseif ($this->version === 'v3') {
            $dataObj = wp_umbrella_get_service('BackupManageProcessCustomTable')->getBackupData();
        }

        try {
            $destination = sprintf('%s/%s/tables', $scratchDir, $this->getName());

            if (!file_exists($destination)) {
                wp_mkdir_p($destination);
            }

            $currentTable = $dataObj->getTableByCurrentBatch();
            $currentIterator = $dataObj->getBatchIterator('database');

            if ($currentTable === null) {
                return [
                    'success' => true,
                    'iterator_position' => ++$currentIterator,
                    'part' => 0
                ];
            }

            $databaseConfig = [
                $this->database,
                $this->user,
                $this->password,
                $this->host
            ];

            if ($this->sock !== null && !empty($this->sock)) {
                $databaseConfig[] = $this->sock;
            }

            $simpleBackup = SimpleBackup::setDatabase($databaseConfig);

            $tableName = $currentTable['name'];
            $simpleBackup->includeOnly([$tableName]);

            $newPart = 0;

            $filenameExport = sprintf('%s', $tableName);

            $needDropTable = true;

            $batchs = [];
            if ($dataObj->hasTableNeedBatchByName($tableName)) {
                $batchs = $dataObj->getTableBatchsByName($tableName);
                $part = $dataObj->getBatchPart('database');

                if (isset($batchs[$part])) {
                    $newPart = $part + 1;
                    $offset = $batchs[$part]['offset'];
                    $limit = $batchs[$part]['limit'];

                    $needDropTable = $part === 0 ? true : false;

                    $simpleBackup->setTableLimitsOn([
                        $tableName => sprintf('%s, %s', $offset, $limit)
                    ]);

                    $filenameExport .= sprintf('-part-%s', $part);
                } else {
                    return [
                        'success' => true,
                        'iterator_position' => $currentIterator,
                        'part' => $newPart
                    ];
                }
            }

            if ($this->sock !== null && !empty($this->sock)) {
                $simpleBackup->setDbHostSock($this->sock);
            }

            $simpleBackup->setAddDropTable(
                $needDropTable
            );

            $simpleBackup->storeAfterExportTo($destination, $filenameExport);

            if (!empty($batchs) && !isset($batchs[$newPart])) {
                $newPart = 0;
            }

            if ($newPart === 0) {
                $currentIterator++;
            }

            $fileZip = sprintf('%s/%s.zip', $scratchDir, $this->getName());
            $zip = new \ZipArchive();
            $zip->open($fileZip, \ZipArchive::CREATE);

            $zip->addFile(sprintf('%s/%s.sql', $destination, $filenameExport), sprintf('tables/%s.sql', $filenameExport));

            return [
                'success' => true,
                'iterator_position' => $currentIterator,
                'part' => $newPart
            ];
        } catch (\Exception $e) {
            $currentIterator = $dataObj->getBatchIterator('database');

            wp_umbrella_get_service('TaskBackupLogger')->error($e->getMessage(), $dataObj->getUmbrellaBackupId());

            $handle = wp_umbrella_get_service('PreventMaxUserPoolConnection')->execute($e);

            if (!$handle) {
                DataTemporary::setDataByKey('message_error_backup', $e->getMessage());
            }

            $handleTableNotFound = wp_umbrella_get_service('PreventNoTableFound')->execute($e);

            // If no table found, we continue
            if ($handleTableNotFound) {
                return [
                    'success' => true,
                    'iterator_position' => ++$currentIterator,
                    'part' => 0
                ];
            }

            return [
                'success' => false,
                'iterator_position' => ++$currentIterator,
                'part' => 0
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->namer->getName();
    }
}
