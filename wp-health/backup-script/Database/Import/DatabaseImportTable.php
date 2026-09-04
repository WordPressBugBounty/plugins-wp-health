<?php

if (!class_exists('DatabaseImportTable', false)):
    class DatabaseImportTable
    {
        const STATEMENT_APPLIED = 1;
        const STATEMENT_SKIPPED = 2;
        const STATEMENT_FILE_SHIFTED = 3;

        protected $socket;
        protected $allowCollationFallback;

        /**
         * @param UmbrellaWebSocket $socket
         * @param bool $allowCollationFallback Forwarded to UmbrellaCharsetFixer.
         *   Enabled for cloning only so unknown collations (MySQL 8 utf8mb4_0900_*)
         *   are remapped to a supported one; off for in-place restore.
         */
        public function __construct(UmbrellaWebSocket $socket, $allowCollationFallback = false)
        {
            $this->socket = $socket;
            $this->allowCollationFallback = $allowCollationFallback;
        }

        public function filterStatement($statement, array $filters)
        {
            foreach ($filters as $filter) {
                $statement = $filter->filter($statement);
            }
            return $statement;
        }

        public function formatQueryError($message, $statements, $path, $processed, $cursor, $size)
        {
            $max = 2 * 1024;
            $len = strlen($statements);
            if ($len > $max) {
                $statements = substr($statements, 0, $max / 2) . sprintf('[truncated %d bytes]', strlen($statements) - $max) . substr($statements, -$max / 2);
            }
            if (!UmbrellaUTF8::seemsUTF8($statements)) {
                if (function_exists('mb_convert_encoding')) {
                    // http://php.net/manual/en/function.iconv.php#108643
                    ini_set('mbstring.substitute_character', 'none');
                    $statements = mb_convert_encoding($statements, 'UTF-8', 'UTF-8');
                } else {
                    $statements = 'base64:' . base64_encode($statements);
                }
            }
            return sprintf('%s; query: %s (file %s at %d-%d out of %d bytes)', $message, $statements, $path, $processed, $cursor, $size);
        }

        public function import(UmbrellaConnectionInterface $connection, UmbrellaImportState $state, $maxCount = 10000, $filters = [])
        {
            clearstatcache();
            $maxPacket = $realMaxPacket = 0;
            $importErrors = [];
            $successfulTables = [];
            $skippedTables = [];
            $attemptedTables = []; // Track attempted tables to prevent infinite loops

            if (is_array($maxPacketResult = $connection->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->fetch())) {
                $maxPacket = $realMaxPacket = (int)end($maxPacketResult);
            }

            if (!$maxPacket) {
                $maxPacket = 128 << 10;
            } elseif ($maxPacket > 512 << 10) {
                $maxPacket = 512 << 10;
            }

            $shifts = 0;
            // Each file is visited roughly once (drop + recreate together); the FK-order
            // recovery may re-queue a file a few times. Keep generous headroom so a
            // normal run never trips the guard; exhausting it now triggers a resume
            // rather than a silent partial import (see the guard inside the loop).
            $maxAttempts = count($state->files) * 3 + 10;
            $totalAttempts = 0;

            while (($dump = $state->next()) !== null) {
                // Prevent infinite loop: if we've attempted too many times, stop
                if ($totalAttempts >= $maxAttempts) {
                    // With the drop-all-first design the not-yet-recreated tables are
                    // already dropped. Falling through here returns the state to
                    // importTables(), which reports success on a truncated database.
                    if (count($successfulTables) === 0) {
                        // No table completed this run: we are stuck (a table that keeps
                        // erroring). Fail loudly instead of resume-looping forever.
                        $this->socket->sendLog('Maximum import attempts reached with no progress; aborting import.', true);
                        throw new UmbrellaException('Database import made no progress before exhausting the attempt budget', 'db_import_no_progress');
                    }
                    // Progress was made but the budget is spent: persist the cursor and
                    // let the worker resume to finish the remaining tables.
                    $this->socket->sendLog('Maximum import attempts reached; requesting resume to finish the remaining tables.', true);
                    throw new UmbrellaDatabasePreventMaxExecutionTime($this->computeGlobalCursor($state));
                }

                // Time-limit guard (between tables): persist the cursor and let
                // the worker resume rather than running every table in a single
                // execution and getting hard-killed mid-import.
                if ($this->isTimeLimitApproaching()) {
                    throw new UmbrellaDatabasePreventMaxExecutionTime($this->computeGlobalCursor($state));
                }

                $totalAttempts++;

                // Track this specific table attempt
                if (!isset($attemptedTables[$dump->path])) {
                    $attemptedTables[$dump->path] = 0;
                }
                $attemptedTables[$dump->path]++;

                // Skip if we've already tried this table too many times
                if ($attemptedTables[$dump->path] > 3) {
                    $this->socket->sendLog(sprintf(
                        'Skipping table %s after %d failed attempts',
                        $dump->path,
                        $attemptedTables[$dump->path] - 1
                    ), true);

                    if (!in_array($dump->path, $skippedTables)) {
                        $skippedTables[] = $dump->path;
                        $importErrors[] = [
                            'table' => $dump->path,
                            'error' => 'Maximum retry attempts exceeded',
                            'code' => 'max_retries_exceeded',
                            'processed_bytes' => $dump->processed ?? 0,
                            'total_bytes' => $dump->size ?? 0
                        ];
                        $this->socket->sendTelemetryCounter('restore.database.table', [
                            'origin' => 'plugin',
                            'table' => basename($dump->path),
                            'status' => 'skipped',
                            'errorCode' => 'max_retries_exceeded',
                        ]);
                    }
                    continue;
                }

                try {
                    $this->socket->sendLog('Importing table: ' . $dump->path, true);
                    $this->importSingleTable($connection, $state, $dump, $maxCount, $maxPacket, $realMaxPacket, $shifts, $filters);
                    $this->socket->sendLog('Table imported: ' . $dump->path, true);
                    if (!in_array($dump->path, $successfulTables)) {
                        $successfulTables[] = $dump->path;
                        $this->socket->sendTelemetryCounter('restore.database.table', [
                            'origin' => 'plugin',
                            'table' => basename($dump->path),
                            'status' => 'imported',
                        ]);
                    }
                } catch (UmbrellaDatabasePreventMaxExecutionTime $e) {
                    // Not a table failure: propagate so importTables() returns the
                    // cursor and the worker resumes, instead of being swallowed by
                    // the generic catch below and marking the table skipped.
                    throw $e;
                } catch (UmbrellaException $e) {
                    // Log the error but continue with other tables
                    $errorInfo = [
                        'table' => $dump->path,
                        'error' => $e->getMessage(),
                        'code' => $e->getInternalError(),
                        'processed_bytes' => $dump->processed ?? 0,
                        'total_bytes' => $dump->size ?? 0
                    ];

                    if (!in_array($dump->path, array_column($importErrors, 'table'))) {
                        $importErrors[] = $errorInfo;
                        $this->socket->sendTelemetryCounter('restore.database.table', [
                            'origin' => 'plugin',
                            'table' => basename($dump->path),
                            'status' => 'error',
                            'errorCode' => $errorInfo['code'],
                        ]);
                    }

                    // Log the error for debugging
                    $this->socket->sendLog(sprintf(
                        'DatabaseImportTable: Failed to import table %s - %s (code: %s)',
                        $dump->path,
                        $e->getMessage(),
                        $e->getInternalError()
                    ), true);

                    // For certain critical errors, we can decide to stop completely
                    if (in_array($e->getInternalError(), ['different_size', 'db_max_packet_size_reached'])) {
                        throw $e; // Re-throw truly critical errors
                    }

                    // Mark this table as skipped and continue
                    if (!in_array($dump->path, $skippedTables)) {
                        $skippedTables[] = $dump->path;
                    }
                    continue;
                } catch (Exception $e) {
                    // Other non-Umbrella exceptions
                    $errorInfo = [
                        'table' => $dump->path,
                        'error' => $e->getMessage(),
                        'code' => 'unknown',
                        'processed_bytes' => $dump->processed ?? 0,
                        'total_bytes' => $dump->size ?? 0
                    ];

                    if (!in_array($dump->path, array_column($importErrors, 'table'))) {
                        $importErrors[] = $errorInfo;
                        $this->socket->sendTelemetryCounter('restore.database.table', [
                            'origin' => 'plugin',
                            'table' => basename($dump->path),
                            'status' => 'error',
                            'errorCode' => $errorInfo['code'],
                        ]);
                    }

                    $this->socket->sendLog(sprintf(
                        'DatabaseImportTable: Unexpected error importing table %s - %s',
                        $dump->path,
                        $e->getMessage()
                    ), true);

                    if (!in_array($dump->path, $skippedTables)) {
                        $skippedTables[] = $dump->path;
                    }
                    continue;
                }
            }

            // Counts are per-run: a resumed import only reports the tables
            // processed in this execution, so sum by requestId to get the
            // whole restoration.
            $this->socket->sendTelemetryCounter('restore.database.summary', [
                'origin' => 'plugin',
                'tablesImported' => count($successfulTables),
                'tablesSkipped' => count($skippedTables),
                'tablesError' => count($importErrors),
            ]);

            // Add import statistics to the state
            if (method_exists($state, 'setImportStats')) {
                $state->setImportStats([
                    'successful_tables' => $successfulTables,
                    'skipped_tables' => $skippedTables,
                    'errors' => $importErrors,
                    'total_processed' => count($successfulTables) + count($skippedTables),
                    'success_rate' => count($successfulTables) / max(1, count($successfulTables) + count($skippedTables)) * 100
                ]);
            }

            return $state;
        }

        /**
         * The tuple scanner below walks raw bytes and treats 0x5C as an escape
         * byte. character_set_client is what governs how the server tokenises
         * the statement we send, so it is the one that must be ASCII-compatible;
         * character_set_connection is checked too because the three drivers set
         * both together and a mismatch means the session is not what we think.
         *
         * @return bool
         */
        protected function isByteScanConsistentWithServer(UmbrellaConnectionInterface $connection)
        {
            try {
                $row = $connection->query('SELECT @@session.character_set_client AS charset_client, @@session.character_set_connection AS charset_connection, @@session.sql_mode AS mode')->fetch();
            } catch (Exception $e) {
                return false;
            }

            if (!is_array($row)) {
                return false;
            }

            $safeCharsets = ['ascii', 'binary', 'latin1', 'utf8', 'utf8mb3', 'utf8mb4'];
            foreach (['charset_client', 'charset_connection'] as $key) {
                if (!isset($row[$key])) {
                    return false;
                }
                if (!in_array(strtolower($row[$key]), $safeCharsets, true)) {
                    return false;
                }
            }

            if (isset($row['mode']) && strpos(strtoupper($row['mode']), 'NO_BACKSLASH_ESCAPES') !== false) {
                return false;
            }

            return true;
        }

        /**
         * Split an oversized extended INSERT into sub-statements that each fit
         * the server's max_allowed_packet. Returns null when the statement
         * cannot be split safely (not an extended INSERT, parse failure, a
         * single tuple over the cap, or a connection the byte scanner cannot
         * follow) -- the caller then falls back to db_max_packet_size_reached.
         *
         * The scanner rewrites INSERT INTO -> INSERT IGNORE INTO, so the prefix
         * regex accepts the optional IGNORE; the re-emitted sub-INSERTs reuse the
         * captured prefix verbatim.
         *
         * @return array|null
         */
        protected function splitOversizedInsert(UmbrellaConnectionInterface $connection, $statements, $realMaxPacket)
        {
            if (!preg_match('/^\s*(INSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`(?:\s*\([^)]*\))?\s+VALUES\s*)\(/is', $statements, $m)) {
                return null;
            }
            if (!$this->isByteScanConsistentWithServer($connection)) {
                return null;
            }
            $prefix = $m[1];
            $budget = (int) ($realMaxPacket / 2);
            if ($budget < 1024) {
                $budget = 1024;
            }
            if ($budget > $realMaxPacket) {
                // The floor above must never raise the budget over the cap we
                // are splitting to fit, or the sub-INSERTs come out oversized.
                $budget = $realMaxPacket;
            }

            // Body starts at the first tuple's opening parenthesis.
            $body = substr($statements, strlen($m[0]) - 1);
            $body = rtrim(rtrim($body), ";");

            $len = strlen($body);
            $tuples = array();
            $depth = 0;
            $inString = false;
            $start = 0;
            // End offset of the previously closed depth-0 group. Everything
            // between two groups must be a plain comma separator, otherwise the
            // statement carries a trailing clause (ON DUPLICATE KEY UPDATE, a
            // second statement, ...) that re-emitting the tuples would drop.
            $gapStart = 0;
            for ($i = 0; $i < $len; $i++) {
                $c = $body[$i];
                if ($inString) {
                    if ($c === '\\') {
                        $i++;
                        continue;
                    }
                    if ($c === "'") {
                        $inString = false;
                    }
                    continue;
                }
                if ($c === "'") {
                    $inString = true;
                    continue;
                }
                if ($c === '(') {
                    if ($depth === 0) {
                        $gap = substr($body, $gapStart, $i - $gapStart);
                        $separator = count($tuples) === 0 ? '{^\s*$}' : '{^\s*,\s*$}';
                        if (!preg_match($separator, $gap)) {
                            return null;
                        }
                        $start = $i;
                    }
                    $depth++;
                    continue;
                }
                if ($c === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $tuples[] = substr($body, $start, $i - $start + 1);
                        $gapStart = $i + 1;
                    }
                }
            }
            if ($depth !== 0 || $inString || count($tuples) === 0) {
                return null;
            }
            if (!preg_match('{^\s*$}', substr($body, $gapStart))) {
                return null;
            }

            $queries = array();
            $batch = array();
            $batchBytes = 0;
            foreach ($tuples as $tuple) {
                $tupleLen = strlen($tuple) + 1;
                if (strlen($prefix) + $tupleLen + 20 > $realMaxPacket) {
                    // A single row bigger than the packet: nothing to split.
                    return null;
                }
                if ($batchBytes > 0 && strlen($prefix) + $batchBytes + $tupleLen + 20 > $budget) {
                    $queries[] = $prefix . implode(',', $batch) . ';';
                    $batch = array();
                    $batchBytes = 0;
                }
                $batch[] = $tuple;
                $batchBytes += $tupleLen;
            }
            if (count($batch) > 0) {
                $queries[] = $prefix . implode(',', $batch) . ';';
            }
            return $queries;
        }

        /**
         * Execute one statement and, on failure, run the recovery switch against
         * that statement only.
         *
         * @param string $statements
         *
         * @return int One of the STATEMENT_* constants.
         */
        protected function executeStatementWithRecovery(UmbrellaConnectionInterface $connection, UmbrellaImportState $state, $dump, $scanner, $charsetFixer, $statements, &$shifts, $realMaxPacket)
        {
            try {
                $connection->execute($statements);
                $shifts = 0;

                return self::STATEMENT_APPLIED;
            } catch (UmbrellaException $e) {
                // Super-powerful recovery switch, un-document it to secure your job.
                switch ($e->getInternalError()) {
                    case '1005': // SQLSTATE[HY000]: General error: 1005 Can't create table 'dbname.wp_wlm_email_queue' (errno: 150)
                        // This looks like an issue specific to InnoDB storage engine.
                    case '1451': // SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails
                        // For "DROP TABLE IF EXISTS..." queries. Sometimes they DO exist.
                    case '1217': // Cannot delete or update a parent row: a foreign key constraint fails
                        // @todo we could drop keys before dropping the database, but we would have to parse SQL :/
                    case '1146': // Table '%s' doesn't exist
                    case '1824': // Failed to open the referenced table '%s'
                    case '1215': // Cannot add foreign key constraint
                        // Possible table reference error, we should suspend this import and go to next file.
                        // Push the currently imported file to end if and only if we're certain that the number of pushes
                        // without a successful statement execution doesn't exceed the number of files being imported;
                        // that would mean that we rotated all the files and would enter an infinite loop.
                        if ($shifts + 1 < count($state->files)) {
                            // Switch to next file.
                            $state->pushNextToEnd();
                            $scanner->close();
                            $shifts++;

                            return self::STATEMENT_FILE_SHIFTED;
                        }
                        throw new UmbrellaException($this->formatQueryError($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                    case '1115':
                    case '1273':
                        $newStatements = preg_replace_callback('{utf8mb4[a-z0-9_]*}', [$charsetFixer, 'replaceCharsetOrCollation'], $statements, -1, $count);
                        if ($count) {
                            try {
                                $connection->execute($newStatements);

                                return self::STATEMENT_APPLIED;
                            } catch (UmbrellaException $e2) {
                            }
                        }
                        throw new UmbrellaException($this->formatQueryError($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                    case '2013':
                        // 2013 Lost connection to MySQL server during query
                    case '2006':
                        // 2006 MySQL server has gone away
                    case '1153':
                        // SQLSTATE[08S01]: Communication link failure: 1153 Got a packet bigger than 'max_allowed_packet' bytes
                        $attempt = 1;
                        $maxAttempts = 4;
                        while (++$attempt <= $maxAttempts) {
                            usleep(100000 * pow($attempt, 2));
                            try {
                                $connection->close();
                                // Reconnecting resets session state; re-apply the import
                                // session settings so FK-bearing tables don't start
                                // erroring and burning the attempt budget.
                                try {
                                    $connection->execute("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
                                    $connection->execute('SET SESSION FOREIGN_KEY_CHECKS = 0');
                                } catch (Exception $eReset) {
                                    // best effort, the retry below re-establishes the connection anyway
                                }
                                if ($realMaxPacket && (strlen($statements) * 1.2) > $realMaxPacket) {
                                    // We are certain that the packet size is too big.
                                    $connection->execute(sprintf('SET GLOBAL max_allowed_packet=%d', strlen($statements) + 1024 * 1024));
                                }
                                $connection->execute($statements);

                                return self::STATEMENT_APPLIED;
                            } catch (Exception $e2) {
                                trigger_error(sprintf('Could not increase max_allowed_packet: %s for file %s at offset %d', $e2->getMessage(), $dump->path, $scanner->tell()));
                            }
                        }
                        // We aren't certain of what happened here. Maybe reconnect once?
                        throw new UmbrellaException($this->formatQueryError($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                    case '1231':
                        // Ignore errors like this:
                        // SQLSTATE[42000]: Syntax error or access violation: 1231 Variable 'character_set_client' can't be set to the value of 'NULL'
                        // We don't save the SQL variable state between imports since we only care about the relevant ones (encoding, timezone).
                        return self::STATEMENT_SKIPPED;
                    case '1067': // SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid default value for 'access_granted'
                        // Most probably NO_ZERO_DATE is ON and the default value is something like 0000-00-00.
                        $currentMode = $connection->query('SELECT @@sql_mode')->fetch();
                        $currentMode = @end($currentMode);
                        if (strlen($currentMode)) {
                            $modes = explode(',', $currentMode);
                            $removeModes = ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE'];
                            foreach ($modes as $i => $mode) {
                                if (!in_array($mode, $removeModes)) {
                                    continue;
                                }
                                unset($modes[$i]);
                            }
                            $newMode = implode(',', $modes);
                            try {
                                $connection->execute("SET SESSION sql_mode = '$newMode'");
                                $connection->execute($statements);

                                // Recovered.
                                return self::STATEMENT_APPLIED;
                            } catch (Exception $e2) {
                                trigger_error($e2->getMessage());
                            }
                        }
                        throw new UmbrellaException($this->formatQueryError($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                    case '1064':
                        // MariaDB compatibility cases.
                        // This is regarding the PAGE_CHECKSUM property.
                    case '1286':
                        // ... and this is regarding the unknown storage engine, e.g.:
                        // CREATE TABLE `name` ( ... ) ENGINE=Aria  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci PAGE_CHECKSUM=1;
                        // results in
                        // SQLSTATE[42000]: Syntax error or access violation: 1286 Unknown storage engine 'Aria'
                        if (strpos($statements, 'PAGE_CHECKSUM') !== false) {
                            // MariaDB's CREATE TABLE statement has some options
                            // that MySQL doesn't recognize.
                            $connection->query(strtr($statements, [
                                ' ENGINE=Aria ' => ' ENGINE=MyISAM ',
                                ' PAGE_CHECKSUM=1' => '',
                                ' PAGE_CHECKSUM=0' => '',
                            ]));

                            return self::STATEMENT_APPLIED;
                        }
                        throw new UmbrellaException($this->formatQueryError($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                    case '1298':
                        // 1298 Unknown or incorrect time zone
                        return self::STATEMENT_SKIPPED;
                    case '1419':
                        // Triggers require super-user permissions.
                        $state->skipStatement($statements);

                        return self::STATEMENT_SKIPPED;
                    case '1227':
                        if (strncmp($statements, 'SET @@SESSION.', 14) === 0 || strncmp($statements, 'SET @@GLOBAL.', 13) === 0) {
                            // SET @@SESSION.SQL_LOG_BIN= 0;
                            // SET @@GLOBAL.GTID_PURGED='';
                            return self::STATEMENT_SKIPPED;
                        }
                        // Remove strings like DEFINER=`user`@`localhost`, because they generate errors like this:
                        // "[1227] Access denied; you need (at least one of) the SUPER privilege(s) for this operation"
                        $newStatements = preg_replace('{(/\*!\d+) DEFINER=`[^`]+`@`[^`]+`(\*/ )}', '', $statements, 1, $count);
                        if ($count) {
                            try {
                                $connection->execute($newStatements);

                                return self::STATEMENT_APPLIED;
                            } catch (UmbrellaException $e2) {
                            }
                        }

                        if ($dump->type === UmbrellaTableType::PROCEDURE || $dump->type === UmbrellaTableType::FUNC || $dump->type === UmbrellaTableType::VIEW) {
                            // Try for procedure, function or view to remove strings like DEFINER=`user`@`localhost`
                            // If it fails just continue, we don't want to break due to problem with functions, procedures or views
                            $newStatements = preg_replace('{DEFINER=`[^`]+`@`[^`]+`}', '', $statements, 1, $count);
                            if ($count) {
                                try {
                                    $connection->execute($newStatements);

                                    return self::STATEMENT_APPLIED;
                                } catch (UmbrellaException $e2) {
                                    $state->skipStatement($statements);
                                }
                            }

                            return self::STATEMENT_SKIPPED;
                        }

                        throw new UmbrellaException($this->formatQueryError($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                    case '3167':
                        if (strpos($statements, '@is_rocksdb_supported') !== false) {
                            // /*!50112 SELECT COUNT(*) INTO @is_rocksdb_supported FROM INFORMATION_SCHEMA.SESSION_VARIABLES ... */;
                            // #3167 - The 'INFORMATION_SCHEMA.SESSION_VARIABLES' feature is disabled
                            try {
                                $connection->execute('SET @is_rocksdb_supported = 0');
                            } catch (UmbrellaException $e2) {
                                throw new UmbrellaException('Could not recover from RocksDB support patch: ' . $e2->getMessage());
                            }

                            return self::STATEMENT_SKIPPED;
                        }
                        throw new UmbrellaException($e->getMessage(), 'db_query_error');
                    default:
                        if ($dump->type !== UmbrellaTableType::REGULAR) {
                            $state->skipStatement($statements);

                            return self::STATEMENT_SKIPPED;
                        }
                        throw new UmbrellaException($e->getMessage(), 'db_query_error');
                }
            } catch (Exception $e) {
                error_log($e->getMessage());

                return self::STATEMENT_SKIPPED;
            }

            return self::STATEMENT_SKIPPED;
        }

        protected function importSingleTable(UmbrellaConnectionInterface $connection, UmbrellaImportState $state, $dump, $maxCount, $maxPacket, $realMaxPacket, &$shifts, $filters = [])
        {
            // if (strlen($dump->encoding)) {
            //     $connection->execute('SET NAMES utf8');
            // }

            $filePath = $dump->path;
            $stat = getFsStat($filePath);

            if ($stat->getSize() !== $dump->size) {
                throw new UmbrellaException(sprintf("Inconsistent table dump file size, file %s transferred %d bytes, but on the disk it's %d bytes", $dump->path, $dump->size, $stat->getSize()), 'different_size');
            }
            $scanner = new UmbrellaDumpScanner($filePath);

            if ($dump->processed !== 0) {
                $scanner->seek($dump->processed);
            }

            // Drop-then-recreate each table in place, then move on. We do NOT
            // drop every table up front: FOREIGN_KEY_CHECKS is disabled for the
            // import so per-table ordering is safe, and dropping all tables
            // first meant an import interrupted mid-way left the whole tail of
            // tables dropped-but-never-recreated (silent data loss). Restoring
            // each table fully before the next bounds worst-case loss to the
            // single table in flight, which resume re-imports from its DROP.
            $charsetFixer = new UmbrellaCharsetFixer($connection, $this->allowCollationFallback);
            while (strlen($statements = $scanner->scan($maxCount, $maxPacket))) {
                if (preg_match('{^\s*(?:/\\*!\d+\s*)?set\s+(?:character_set_client\s*=|names\s+)}i', $statements)) {
                    // Skip all the /*!40101 SET character_set_client=*** */; statements.
                    continue;
                }

                $statements = $this->filterStatement($statements, $filters);

                // Remove PHP die statement if present
                $statements = preg_replace('/^--\s*<\?php\s+die\(\);\s*\?>/i', '', $statements);

                // Split AFTER filtering so the sub-statements carry exactly the
                // bytes we would otherwise have sent, and so the size check sees
                // the final length.
                $batches = [$statements];
                $isSplit = false;
                if ($realMaxPacket && strlen($statements) + 20 > $realMaxPacket) {
                    // The dump batches rows by count, so a table with large rows
                    // can produce a single extended INSERT bigger than the target
                    // server's max_allowed_packet. Split its top-level tuples into
                    // sub-INSERTs under the cap instead of aborting the whole import.
                    $oversizedBatches = $this->splitOversizedInsert($connection, $statements, $realMaxPacket);
                    if ($oversizedBatches === null) {
                        throw new UmbrellaException(sprintf("A query in the backup (%d bytes) is too big for the SQL server to process (max %d bytes); please set the server's variable 'max_allowed_packet' to at least %d and retry the process", strlen($statements), $realMaxPacket, strlen($statements) + 20), 'db_max_packet_size_reached', strlen($statements));
                    }
                    $batches = $oversizedBatches;
                    $isSplit = true;
                }

                foreach ($batches as $batch) {
                    $result = $this->executeStatementWithRecovery($connection, $state, $dump, $scanner, $charsetFixer, $batch, $shifts, $realMaxPacket);

                    if ($result === self::STATEMENT_FILE_SHIFTED) {
                        return;
                    }

                    if ($isSplit && $result !== self::STATEMENT_APPLIED) {
                        // Rows of this sub-INSERT never landed. Fail here so the
                        // byte cursor below is not reached: advancing it would
                        // drop the remaining sub-INSERTs of the chunk and report
                        // the restore as finished.
                        throw new UmbrellaException($this->formatQueryError('A sub-statement of an oversized INSERT was not applied', $batch, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error');
                    }
                }

                $dump->processed = $scanner->tell();

                // Time-limit guard (mid-table): a single large dump (e.g.
                // wp_postmeta) can exceed PHP's max execution time on its own.
                // Persist the byte cursor and bail out so the worker resumes the
                // import from here. Without this the PHP process is hard-killed
                // mid-import: restore.database.finished is never emitted, no
                // restore.error is raised, and the worker (which only waits on the
                // connection ACK) reports the restoration as successful while the
                // database was only partially applied.
                if ($this->isTimeLimitApproaching()) {
                    try {
                        $connection->execute('UNLOCK TABLES');
                    } catch (Exception $e) {
                        // Ignore, we're interrupting the import anyway.
                    }
                    $scanner->close();
                    throw new UmbrellaDatabasePreventMaxExecutionTime($this->computeGlobalCursor($state));
                }
            }

            $dump->processed = $scanner->tell();
            $scanner->close();
        }

        /**
         * Whether we are about to hit PHP's max execution time. Relies on the
         * $startTimer / $safeTimeLimit globals set by the restore script.php.
         * Falls back to "never" if they are not defined, preserving the previous
         * single-shot behaviour.
         *
         * @return bool
         */
        protected function isTimeLimitApproaching()
        {
            global $startTimer, $safeTimeLimit;

            if (empty($startTimer) || empty($safeTimeLimit) || (int) $safeTimeLimit < 1) {
                return false;
            }

            return (time() - $startTimer) >= $safeTimeLimit;
        }

        /**
         * Resume cursor as a glob-order PREFIX byte offset, matching exactly how
         * DatabaseRestoration redistributes UMBRELLA_DATABASE_CURSOR on resume:
         * it re-globs the *.sql files (PHP sorts glob() alphabetically) and fills
         * each file's `processed` in that order until the cursor is exhausted.
         *
         * It must therefore be a single contiguous prefix: the sum of the sizes
         * of the fully-imported leading files plus the partial offset of the
         * FIRST not-yet-complete file. We must NOT simply sum every file's
         * `processed`, because import() reorders $state->files (DROP TABLE first
         * for FK safety, via pushNextToEnd), so a later glob file can already
         * carry a few bytes (its own DROP) while an earlier file is still in
         * progress. Summing those bytes would push the redistributed offset past
         * a statement boundary and seek() would land mid-line, corrupting the
         * resumed import. Any progress on files after the first incomplete one is
         * intentionally dropped; those files are re-imported from scratch on
         * resume (idempotent, each dump starts with DROP TABLE IF EXISTS).
         *
         * @return int
         */
        protected function computeGlobalCursor(UmbrellaImportState $state)
        {
            $files = $state->files;
            usort($files, function ($a, $b) {
                return strcmp($a->path, $b->path);
            });

            $cursor = 0;
            foreach ($files as $file) {
                if ((int) $file->processed >= (int) $file->size) {
                    $cursor += (int) $file->size;
                    continue;
                }
                $cursor += (int) $file->processed;
                break;
            }
            return $cursor;
        }
    }
endif;
