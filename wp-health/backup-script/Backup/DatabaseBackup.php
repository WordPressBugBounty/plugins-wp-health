<?php

if (!class_exists('UmbrellaDatabaseBackup', false)):
    class UmbrellaDatabaseBackup extends UmbrellaAbstractProcessBackup
    {
        protected $connection;

        public function __construct($params)
        {
            parent::__construct($params);
            $this->connection = $params['connection'] ?? null;
        }

        public function backup($tables)
        {
            if ($this->connection === null) {
                return;
            }

            global $startTimer, $safeTimeLimit, $totalFilesSent;

            $dumpDatabaseCursor = $this->context->getDatabaseDumpCursor();

            foreach ($tables as $key => $table) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    throw new UmbrellaDatabasePreventMaxExecutionTime($key); // send the cursor to the server
                    break; // Stop if we are close to the time limit
                }

                // Skip first element if dumpDatabaseCursor is not 0
                if ($key === 0 && $dumpDatabaseCursor !== 0) {
                    continue;
                }

                // Skip all elements before dumpDatabaseCursor
                if ($key !== 0 && $key <= $dumpDatabaseCursor) {
                    continue;
                }

                // Check and reconnect if MySQL connection was lost (e.g., after long file upload or sleep)
                if (!$this->connection->ping()) {
                    $this->socket->sendLog('MySQL connection lost, attempting to reconnect...');
                    if ($this->connection->reconnect()) {
                        $this->socket->sendLog('MySQL reconnection successful');
                    } else {
                        $this->socket->sendLog('MySQL reconnection failed', true);
                        throw new UmbrellaException('Failed to reconnect to MySQL after connection loss', 'db_reconnect_failed');
                    }
                }

                $this->socket->sendDatabaseTable($table['name']);

                if ($table['type'] === UmbrellaTableType::REGULAR) {
                    $this->socket->sendLog('Getting table columns for table: ' . $table['name']);
                    $table['columns'] = UmbrellaDatabaseFunction::getTableColumns($this->connection, $table['name']);
                    $this->socket->sendLog('Finished getting table columns for table: ' . $table['name']);
                }

                $tablePath = $this->context->getRootDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . $table['name'] . '.sql';

                try {
                    if ($this->context->getIsSqlPartitioned()) {
                        $maxMoPerFile = $this->context->getMaxMoPerFile();
                        // Convert MB to bytes (1024 * 1024)
                        $maxSizeBytes = $maxMoPerFile * 1024 * 1024;

                        $fileHandle = new UmbrellaRotatingFileHandle($tablePath, $maxSizeBytes, function ($path) {
                            $this->socket->send($path);
                            @unlink($path);
                        }, $table['name']);
                    } else {
                        $fileHandle = new UmbrellaFileHandle($tablePath, 'wb');
                        if ($fileHandle->isInError()) {
                            $this->socket->sendLog('File handle in error: ' . $table['name'], true);
                            continue;
                        }
                    }

                    $maximumLinesByTableByBatch = $this->context->getMaximumLinesByTableByBatch();
                    $batchSize = $maximumLinesByTableByBatch[$table['name']] ?? 0;

                    switch ($table['type']) {
                        default:
                            $table['size'] = UmbrellaSqlInstruction::dumpTupleTable(
                                $this->connection,
                                $table,
                                $fileHandle,
                                $this->socket,
                                $batchSize,
                                $this->context->getIntervalBetweenBatch()
                            );
                            break;
                    }

                    $this->socket->sendTelemetryCounter('backup.db.table', [
                        'requestId' => $this->context->getRequestId(),
                        'origin' => 'plugin',
                        'name' => $table['name'],
                        'size' => isset($table['size']) ? $table['size'] : 0,
                        'batchSize' => $batchSize,
                        'type' => $table['type'],
                    ]);

                    if ($this->context->getIsSqlPartitioned()) {
                        $fileHandle->close();
                        // Remove directory if empty? The parts are unlinked.
                        // Ideally we should remove the directory after all parts are sent and unlinked.
                        $tableDirectory = $this->context->getRootDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . $table['name'];
                        if (is_dir($tableDirectory)) {
                            @rmdir($tableDirectory);
                        }
                    } else {
                        $fileHandle->close();

                        $sent = $this->socket->send($tablePath);

                        if ($sent) {
                            $this->socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SEND_DATABASE_SEND_TABLE, [
                                'table' => $table['name']
                            ]);
                        }
                        @unlink($tablePath);
                    }
                } catch (Exception $e) {
                    $this->socket->sendLog($e->getMessage(), true);
                    //TODO: send error dump data
                }

                $this->socket->sendDatabaseDumpCursor($key);

                if (function_exists('ob_flush') && @ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();

                if ($this->context->getIntervalBetweenBatch() > 0) {
                    $seconds = floor($this->context->getIntervalBetweenBatch());
                    $nanoseconds = ($this->context->getIntervalBetweenBatch() - $seconds) * 1000000000;
                    $this->socket->sendLog('Sleeping for ' . $seconds . ' seconds');
                    time_nanosleep((int)$seconds, (int)$nanoseconds);
                }
            }
        }
    }
endif;
