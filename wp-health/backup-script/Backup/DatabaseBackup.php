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

                if ($table['type'] === UmbrellaTableType::REGULAR) {
                    $table['columns'] = UmbrellaDatabaseFunction::getTableColumns($this->connection, $table['name']);
                }

                $tablePath = $this->context->getRootDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . $table['name'] . '.sql';

                try {
                    $fileHandle = new UmbrellaFileHandle($tablePath, 'wb');
                    if ($fileHandle->isInError()) {
                        continue;
                    }

                    switch ($table['type']) {
                        default:
                            $table['size'] = UmbrellaSqlInstruction::dumpTable($this->connection, $table, $fileHandle);
                            break;
                    }
                } catch (Exception $e) {
                    //TODO: send error dump data
                }

                $fileHandle->close();

                $sent = $this->socket->send($tablePath);
                @unlink($tablePath);
                $this->socket->sendDatabaseDumpCursor($key);

                if (function_exists('ob_flush') && @ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }
    }
endif;
