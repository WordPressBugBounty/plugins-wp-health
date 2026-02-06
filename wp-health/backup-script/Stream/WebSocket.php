<?php

if (!class_exists('UmbrellaWebSocket', false)):
    class UmbrellaWebSocket
    {
        protected $host;
        protected $port;
        protected $wsVersion;
        protected $key;
        protected $connection;
        protected $transport;
        protected $timeout;
        protected $origin;
        /**
         * @var UmbrellaContext
         */
        protected $context;

        const READ_CHUNK_SIZE = 1024 * 10;

        public function __construct($params)
        {
            $this->host = $params['host'];
            $this->port = $params['port'];
            $this->key = $params['key'] ?? base64_encode($this->secureRandomBytes(16));

            $this->wsVersion = $params['wsVersion'] ?? 13;
            $this->transport = $params['transport'] ?? 'tcp';
            $this->timeout = $params['timeout'] ?? 25;
            $this->origin = $params['origin'] ?? $_SERVER['HTTP_HOST'];
            $this->context = $params['context'] ?? null;
        }

        protected function secureRandomBytes($length)
        {
            // If OpenSSL is available
            if (function_exists('openssl_random_pseudo_bytes')) {
                $bytes = openssl_random_pseudo_bytes($length, $strong);
                if ($bytes !== false && $strong === true) {
                    return $bytes;
                }
            }

            // If random_bytes() (PHP 7+) is available
            if (function_exists('random_bytes')) {
                try {
                    return random_bytes($length);
                } catch (Exception $e) {
                    // nore and continue to fallback
                }
            }

            // If /dev/urandom is available (on Linux/Unix)
            $urandom = @fopen('/dev/urandom', 'rb');
            if ($urandom !== false) {
                $bytes = fread($urandom, $length);
                fclose($urandom);
                if ($bytes !== false) {
                    return $bytes;
                }
            }

            // Final fallback (not crypto-safe)
            $bytes = '';
            for ($i = 0; $i < $length; $i++) {
                $bytes .= chr(mt_rand(0, 255));
            }

            return $bytes;
        }

        protected function buildHeaders()
        {
            $headers = [
                'GET / HTTP/1.1',
                'Host: ' . $this->host,
                'Upgrade: websocket',
                'Connection: Upgrade',
                'Origin: ' . $this->origin,
                'X-Request-Id: ' . $this->context->getRequestId(),
                'X-File-Batch-Not-Started: ' . $this->context->hasFileBatchNotStarted(),
                'X-File-Cursor: ' . $this->context->getFileCursor(), // Use on directory dictionary file
                'X-Database-Cursor: ' . $this->context->getDatabaseCursor(), // Use for database export
                'X-Database-Dump-Cursor: ' . $this->context->getDatabaseDumpCursor(), // Use for database dump
                'X-Retry-From-Websocket-Server: ' . $this->context->getRetryFromWebsocketServer(),
                'X-Scan-Cursor: ' . $this->context->getScanCursor(), // Use for scan all files and get the dictionary
                'X-Scan-Directory-Cursor: ' . $this->context->getScanDirectoryCursor(), // Use to know which directory we need to backup
                'X-Checkup-Directory-Cursor: ' . $this->context->getCheckupDirectoriesCursor(),
                'X-Internal-Request: ' . $this->context->getInternalRequest(),
                'X-Ack-Code: ' . $this->context->getAck(),
                'X-Action: ' . $this->context->getAction(),
                'Sec-WebSocket-Key: ' . $this->key,
                'Sec-WebSocket-Version: ' . $this->wsVersion,
            ];

            return implode("\r\n", $headers) . "\r\n\r\n";
        }

        public function connect()
        {
            if (function_exists('stream_socket_client')) {
                $this->connection = @stream_socket_client($this->transport . '://' . $this->host . ':' . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
            } else {
                $this->connection = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
            }

            if (!$this->connection) {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                    'socket' => [
                        'bindto' => '0.0.0.0:0', // force IPv4
                    ],
                ]);

                if (function_exists('stream_socket_client')) {
                    $this->connection = @stream_socket_client(
                        $this->transport . '://' . $this->host . ':' . $this->port,
                        $errno,
                        $errstr,
                        $this->timeout,
                        STREAM_CLIENT_CONNECT,
                        $context
                    );
                } else {
                    $this->connection = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
                }
            }

            if (!$this->connection) {
                throw new UmbrellaSocketException($errstr, $errno);
                return false;
            }

            socket_set_timeout($this->connection, $this->timeout);

            fwrite($this->connection, $this->buildHeaders());

            $response = fgets($this->connection);
            if (strpos($response, 'Unauthorized') !== false) {
                $this->close();
                throw new UmbrellaSocketException('connection_failed', 'Connection failed');
            }

            return true;
        }

        public function writeFrame($message, $isBinary = false)
        {
            $mask = pack('N', rand(1, 2147483647));
            $maskedMessage = $message ^ str_repeat($mask, ceil(strlen($message) / 4));

            $frame = $isBinary ? chr(130) : chr(129); // 0x2 pour binary frame, 0x1 pour text frame
            $len = strlen($maskedMessage);
            if ($len <= 125) {
                $frame .= chr($len | 0x80);
            } elseif ($len <= 65535) {
                $frame .= chr(126 | 0x80) . pack('n', $len);
            } else {
                $frame .= chr(127 | 0x80) . pack('J', $len);
            }
            $frame .= $mask . $maskedMessage;
            unset($mask, $maskedMessage);

            stream_set_timeout($this->connection, $this->timeout);
            // Check if the connection is still open
            if (feof($this->connection)) {
                // It's better to send this exception because we can't send any message to the server
                // We could create like a "safe exception" to handle this case and not End the process
                throw new UmbrellaPreventMaxExecutionTime();
            }

            $written = @fwrite($this->connection, $frame);
            if ($written === false) {
                // It's better to send this exception because we can't send any message to the server
                // We could create like a "safe exception" to handle this case and not End the process
                throw new UmbrellaPreventMaxExecutionTime();
            }

            unset($frame);
        }

        public function sendError(UmbrellaDefaultException $e)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'error_code' => $e->getErrorStrWithCode(),
                'error_message' => $e->getErrorMessage(),
            ]);

            $this->writeFrame('ERROR:' . $data);
        }

        public function sendFileCursor($cursor)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('FILE_CURSOR:' . $data);
        }

        public function sendStartCheckupDirectory($directory)
        {
            $this->sendMessage('__CHECKUP_DIRECTORY_START__', [
                'directory' => $directory,
            ]);
        }

        public function sendFileCheckupDirectory($filePath)
        {
            $this->sendMessage('__CHECKUP_DIRECTORY_FILE__', [
                'filePath' => $filePath,
            ]);
        }

        public function sendEndCheckupDirectory($directory)
        {
            $this->sendMessage('__CHECKUP_DIRECTORY_END__', [
                'directory' => $directory,
            ]);
        }

        public function sendFinishScanIntegrity($filePath)
        {
            $this->send($filePath);

            $this->sendMessage('__FINISH_SCAN_INTEGRITY__');
        }

        public function sendScanCursor($cursor)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('SCAN_CURSOR:' . $data);
        }

        public function sendScanDirectoryCursor($cursor)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('SCAN_DIRECTORY_CURSOR:' . $data);
        }

        public function sendDatabaseDumpCursor($cursor)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('DATABASE_DUMP_CURSOR:' . $data);
        }

        public function sendDatabaseTable($tableName)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'tableName' => $tableName,
            ]);

            $this->writeFrame('__DATABASE_TABLE__:' . $data);
        }

        public function sendBackupFilesFinished()
        {
            $this->sendMessage('__BACKUP_FILES_FINISHED__');
        }

        public function sendFinish()
        {
            if ($this->connection === null) {
                return;
            }

            $this->writeFrame('FINISH');
        }

        public function sendChecksum($directory, $checksum)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'directory' => $directory,
                'checksum' => $checksum,
            ]);
            $this->writeFrame('CHECKSUM:' . $data);

            // return $this->waitForAckChecksum($directory);
        }

        public function sendStructuredLog($code, $context = [])
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'code' => $code,
                'context' => $context,
            ]);
            $this->writeFrame('STRUCTURED_LOG:' . $data);
        }

        public function sendLog($message, $internal = false)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'message' => $message,
                'internal' => $internal,
            ]);
            $this->writeFrame('LOG:' . $data);
        }

        /**
         * Send the telemetry informations.
         *
         * @param string $name Counter name.
         * @param array $attributes Counter attributes.
         *
         * @return void
         * @throws UmbrellaPreventMaxExecutionTime
         * @throws UmbrellaSocketException
         */
        public function sendTelemetryCounter($name, $attributes)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'name' => $name,
                'attributes' => $attributes,
            ]);
            $this->writeFrame('TELEMETRY:' . $data);
        }

        public function sendPreventMaxExecutionTime($cursor = 0)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('PREVENT_MAX_EXECUTION_TIME:' . $data);
        }

        public function sendPreventDatabaseMaxExecutionTime($cursor)
        {
            if ($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('PREVENT_DATABASE_MAX_EXECUTION_TIME:' . $data);
        }

        /**
         * @param string $message
         * @param array $data
         *
         * @return void
         * @throws UmbrellaPreventMaxExecutionTime
         * @throws UmbrellaSocketException
         */
        protected function sendMessage($message, $data = [])
        {
            if ($this->connection === null) {
                return;
            }

            if (count($data) == 0) {
                $this->writeFrame($message);
                return;
            }

            $data = json_encode($data);

            $this->writeFrame("$message:" . $data);
        }

        public function waitForAck($filename)
        {
            $startTime = time();
            $timeout = 60;

            while (time() - $startTime < $timeout) {
                $data = $this->readFrameJson();

                if ($data && $data['type'] === 'ACK' && $data['filename'] === $filename) {
                    return true;
                }
            }

            return false;
        }

        public function waitForAckChecksum($directory)
        {
            $startTime = time();
            $timeout = 30;

            while (time() - $startTime < $timeout) {
                $data = $this->readFrameJson();

                if ($data && $data['type'] === 'ACK_CHECKSUM' && $data['directory'] === $directory) {
                    return true;
                }
            }

            return false;
        }

        public function send($filePath)
        {
            if (!file_exists($filePath)) {
                return;
            }

            $relativePath = substr($filePath, strlen($this->context->getBaseDirectory()) + 1);

            if (!UmbrellaUTF8::seemsUTF8($relativePath)) {
                $relativePath = UmbrellaUTF8::encodeNonUTF8($relativePath);
            }

            if ($relativePath !== '/') {
                $relativePath = str_replace('\\', '/', $relativePath);
            }

            $sequence = 0;
            try {
                if (file_exists($filePath)) {
                    $fileHandle = fopen($filePath, 'rb');

                    if ($fileHandle === false) {
                        $this->sendLog('Error sending file: ' . $filePath, true);
                        return false;
                    }

                    while (!feof($fileHandle)) {
                        $chunk = fread($fileHandle, 8192);
                        $message = json_encode([
                            'type' => 'FILE_CHUNK',
                            'sequence' => $sequence++,
                            'filename' => $relativePath,
                            'data' => base64_encode($chunk)
                        ]);
                        $this->writeFrame($message, false);
                    }

                    $size = filesize($filePath);

                    $endOfFileMessage = json_encode([
                        'type' => 'END_FILE',
                        'filename' => $relativePath,
                        'size' => $size,
                    ]);

                    $limits = $this->context->getFileSizeLimits();

                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                    $limit = UmbrellaContext::DEFAULT_FILE_SIZE_LIMIT;

                    if (key_exists('*', $limits)) {
                        $limit = $limits['*'];
                    }

                    if (key_exists($extension, $limits)) {
                        $limit = $limits[$extension];
                    }

                    if ($size > $limit) {
                        $this->sendTelemetryCounter('backup.file', [
                            'requestId' => $this->context->getRequestId(),
                            'origin' => 'plugin',
                            'filename' => $relativePath,
                            'size' => $size,
                        ]);
                    }

                    $this->writeFrame($endOfFileMessage, false);

                    fclose($fileHandle);

                    return $this->waitForAck($relativePath);
                }
            } catch (\Exception $e) {
                $this->sendLog('Error while sending file: ' . $filePath, true);
                echo 'Error while sending file: ' . $filePath . "\n";
                return false;
            }
        }

        /**
         * @deprecated Use sendFinishDictionaryWithIntegrity instead
         */
        public function sendFinishDictionary()
        {
            if ($this->connection === null) {
                return;
            }

            $this->writeFrame('FINISH_DICTIONARY');
        }

        public function sendFinishDictionaryWithIntegrity($filePath)
        {
            if ($this->connection === null) {
                return;
            }

            $this->send($filePath);

            $this->writeFrame('__FINISH_DICTIONARY_WITH_INTEGRITY__');
        }

        public function sendFinishScanSize($filePath)
        {
            if ($this->connection === null) {
                return;
            }

            $this->send($filePath);

            $this->writeFrame('__FINISH_SCAN_SIZE__');
        }

        public function readFrame()
        {
            $response = fread($this->connection, self::READ_CHUNK_SIZE);
            return $response;
        }

        public function readFrameJson()
        {
            $response = $this->readFrame();
            return $this->decodeWebSocketPayloadToJson($response);
        }

        public function decodeWebSocketPayloadToJson($message)
        {
            // Clean the message from non-printable characters
            $message = trim($message);

            // Find the first '{' character
            $startOfJson = strpos($message, '{');
            if ($startOfJson === false) {
                return null;
            }

            // Cut the message to get only the JSON payload
            $jsonPayload = substr($message, $startOfJson);

            // Remove the first character if it is a comma
            if (substr($jsonPayload, 1, 1) == '{') {
                $jsonPayload = substr($jsonPayload, 1);
            }

            return json_decode($jsonPayload, true);
        }

        public function close()
        {
            if ($this->connection === null) {
                return;
            }

            if (is_resource($this->connection)) {
                fclose($this->connection);
            }

            $this->connection = null;
        }

        public function __destruct()
        {
            $this->close();
        }
    }
endif;
