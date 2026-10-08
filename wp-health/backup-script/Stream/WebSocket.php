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
        protected $readBuffer = '';
        /** relative path => size, files sent whose ACK is still expected */
        protected $inFlight = [];
        protected $inFlightBytes = 0;
        /** what the mirror accepts; 1 until the upgrade response says otherwise */
        protected $ackWindowLimit = 1;
        /** ceiling asked by the worker (ack_window in the request); 1 switches the window off */
        protected $ackWindowCap;
        protected $ackWindow = 1;
        protected $acksSinceGrowth = 0;
        protected $ackTimeout;
        protected $batchEndSupported = false;

        const READ_CHUNK_SIZE = 64 * 1024;
        const ACK_WINDOW_HEADER = 'x-umbrella-ack-window';
        const BATCH_END_HEADER = 'x-umbrella-batch-end';
        const ACK_WINDOW_MAX = 32;
        /**
         * Bytes in flight before waiting for ACKs, whatever the file count: about
         * the bandwidth-delay product of a distant host, so big files never pile
         * up on the mirror.
         */
        const ACK_WINDOW_BYTES = 4194304;
        const ACK_WINDOW_STORE_PREFIX = 'umb_ack_window_';
        const DATABASE_ERROR_DUMP_MESSAGE_LENGTH = 500;

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
            $this->ackTimeout = $params['ack_timeout'] ?? 60;
            $this->ackWindowCap = max(1, min(self::ACK_WINDOW_MAX, (int) ($params['ack_window'] ?? self::ACK_WINDOW_MAX)));
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
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
                'socket' => [
                    'bindto' => '0.0.0.0:0', // force IPv4
                    'tcp_nodelay' => true,
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

            if (!$this->connection) {
                throw new UmbrellaSocketException($errstr, $errno);
                return false;
            }

            socket_set_timeout($this->connection, $this->timeout);

            fwrite($this->connection, $this->buildHeaders());

            $response = $this->readHandshake();
            if (strpos($response, 'Unauthorized') !== false) {
                $this->close();
                throw new UmbrellaSocketException('connection_failed', 'Connection failed');
            }

            $this->configureAckWindow($response);
            $this->configureBatchEnd($response);

            return true;
        }

        /**
         * The whole upgrade response, up to the blank line. Reading only the status
         * line left the other headers in the stream, in front of the first frame.
         */
        protected function readHandshake()
        {
            $response = '';

            while (strpos($response, "\r\n\r\n") === false) {
                $line = fgets($this->connection);
                if ($line === false) {
                    break;
                }
                $response .= $line;
            }

            return $response;
        }

        /**
         * A mirror that can hold several files in flight says so in the upgrade
         * response. Without the header, or with ack_window = 1 in the request, the
         * plugin waits for every ACK, one file at a time, as it always did.
         */
        public function configureAckWindow($handshake)
        {
            $this->ackWindowLimit = 1;

            if (preg_match('/^' . self::ACK_WINDOW_HEADER . ':\s*(\d+)/im', $handshake, $matches)) {
                $this->ackWindowLimit = max(1, min($this->ackWindowCap, (int) $matches[1]));
            }

            $this->ackWindow = min($this->ackWindowLimit, $this->loadAckWindow());
        }

        public function configureBatchEnd($handshake)
        {
            $this->batchEndSupported = (bool) preg_match('/^' . self::BATCH_END_HEADER . ':\s*1/im', $handshake);
        }

        public function supportsBatchEnd()
        {
            return $this->batchEndSupported;
        }

        public function endBatch($seconds)
        {
            if ($this->connection === null || !$this->batchEndSupported) {
                return false;
            }

            $this->writeFrame('__BATCH_END__');

            $deadline = time() + $seconds;
            stream_set_timeout($this->connection, max(1, $seconds));

            while (($payload = $this->readMessage($deadline)) !== null) {
                $data = json_decode($payload, true);
                if (is_array($data) && ($data['type'] ?? '') === 'BATCH_END_ACK') {
                    return true;
                }
            }

            return false;
        }

        public function getAckWindow()
        {
            return $this->ackWindow;
        }

        /** What the mirror and the request allow; 1 = the window is off, not merely reset. */
        public function getAckWindowLimit()
        {
            return $this->ackWindowLimit;
        }

        /**
         * A batch lasts a few dozen files, so the window reached by the previous
         * batch is kept in the checksum scratch directory for the next one.
         */
        protected function getAckWindowStorePath()
        {
            if ($this->context === null || !$this->context->getChecksumDirectory()) {
                return null;
            }

            return rtrim($this->context->getChecksumDirectory(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . self::ACK_WINDOW_STORE_PREFIX
                . preg_replace('/[^a-zA-Z0-9]/', '', (string) $this->context->getRequestId()) . '.txt';
        }

        protected function loadAckWindow()
        {
            $path = $this->getAckWindowStorePath();
            $stored = $path !== null ? (int) @file_get_contents($path) : 0;

            return max(1, $stored);
        }

        protected function persistAckWindow()
        {
            $path = $this->getAckWindowStorePath();
            if ($path !== null) {
                @file_put_contents($path, (string) $this->ackWindow);
            }
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

            if (!is_resource($this->connection)) {
                throw new UmbrellaPreventMaxExecutionTime();
            }
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

        public function sendDatabaseErrorDump($tableName, $message)
        {
            if ($this->connection === null) {
                return;
            }

            $message = substr((string) $message, 0, self::DATABASE_ERROR_DUMP_MESSAGE_LENGTH);
            if (!UmbrellaUTF8::seemsUTF8($message)) {
                $message = UmbrellaUTF8::encodeNonUTF8($message);
            }

            $data = json_encode([
                'table' => $tableName,
                'message' => $message,
            ]);

            $this->writeFrame('DATABASE_ERROR_DUMP:' . $data);
        }

        /**
         * The mirror acts on a finish message at once: it reads the dictionary just
         * sent, walks its tree, closes. Every file in flight must be written and
         * acknowledged first, as it was when send() waited for each ACK.
         */
        protected function drainBeforeFinish()
        {
            if (count($this->inFlight) > 0) {
                $this->drainAcks();
            }
        }

        public function sendBackupFilesFinished()
        {
            $this->drainBeforeFinish();
            $this->sendMessage('__BACKUP_FILES_FINISHED__');
        }

        public function sendFinish()
        {
            if ($this->connection === null) {
                return;
            }

            $this->drainBeforeFinish();
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

        /**
         * Reads ACKs until at most $keep files stay in flight and the bytes in
         * flight are back under the cap. The window doubles each time a window's
         * worth of ACKs came back, up to what the mirror announced; an ACK that
         * never comes drops it back to one file at a time, as before.
         */
        protected function drainAcks($keep = 0)
        {
            $deadline = time() + $this->ackTimeout;
            $received = 0;

            while (count($this->inFlight) > $keep
                || ($this->inFlightBytes >= self::ACK_WINDOW_BYTES && count($this->inFlight) > 0)) {
                $payload = $this->readMessage($deadline);

                if ($payload === null) {
                    $this->inFlight = [];
                    $this->inFlightBytes = 0;
                    $this->ackWindow = 1;
                    $this->acksSinceGrowth = 0;
                    $this->persistAckWindow();

                    return false;
                }

                $data = json_decode($payload, true);
                $filename = is_array($data) && ($data['type'] ?? '') === 'ACK' ? ($data['filename'] ?? '') : '';

                if (!isset($this->inFlight[$filename])) {
                    continue;
                }

                $this->inFlightBytes -= $this->inFlight[$filename];
                unset($this->inFlight[$filename]);
                $received++;
            }

            $this->growAckWindow($received);

            return true;
        }

        protected function growAckWindow($acks)
        {
            $this->acksSinceGrowth += $acks;

            if ($this->ackWindow >= $this->ackWindowLimit || $this->acksSinceGrowth < $this->ackWindow) {
                return;
            }

            $this->ackWindow = min($this->ackWindowLimit, $this->ackWindow * 2);
            $this->acksSinceGrowth = 0;
            $this->persistAckWindow();
        }

        public function send($filePath)
        {
            if (!file_exists($filePath)) {
                return;
            }

            $relativePath = $this->context->getStreamRelativePath($filePath);

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
                        $chunk = fread($fileHandle, 131072);
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

                    $this->inFlight[$relativePath] = $size;
                    $this->inFlightBytes += $size;

                    if (count($this->inFlight) < $this->ackWindow && $this->inFlightBytes < self::ACK_WINDOW_BYTES) {
                        return true;
                    }

                    return $this->drainAcks($this->ackWindow - 1);
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
            $this->drainBeforeFinish();

            $this->writeFrame('__FINISH_DICTIONARY_WITH_INTEGRITY__');
        }

        public function sendFinishScanSize($filePath)
        {
            if ($this->connection === null) {
                return;
            }

            $this->send($filePath);
            $this->drainBeforeFinish();

            $this->writeFrame('__FINISH_SCAN_SIZE__');
        }

        /**
         * The next text message from the mirror, or null when none arrived before
         * the deadline or the connection is gone. Several ACKs often share one read
         * and a frame can straddle two, so the bytes are buffered and framed.
         */
        protected function readMessage($deadline)
        {
            while (true) {
                $frame = $this->extractFrame();

                if ($frame !== null) {
                    if ($frame['opcode'] === 8) {
                        return null;
                    }
                    if ($frame['opcode'] === 1) {
                        return $frame['payload'];
                    }
                    continue;
                }

                if (time() >= $deadline || !is_resource($this->connection) || feof($this->connection)) {
                    return null;
                }

                $data = @fread($this->connection, self::READ_CHUNK_SIZE);
                if ($data === false) {
                    return null;
                }
                if ($data === '') {
                    usleep(10000);
                    continue;
                }

                $this->readBuffer .= $data;
            }
        }

        /** One complete frame from the buffer, or null while it is incomplete. */
        protected function extractFrame()
        {
            $buffer = $this->readBuffer;
            $length = strlen($buffer);

            if ($length < 2) {
                return null;
            }

            $opcode = ord($buffer[0]) & 0x0F;
            $second = ord($buffer[1]);
            $masked = ($second & 0x80) !== 0;
            $payloadLength = $second & 0x7F;
            $offset = 2;

            if ($payloadLength === 126) {
                if ($length < 4) {
                    return null;
                }
                $payloadLength = unpack('n', substr($buffer, 2, 2))[1];
                $offset = 4;
            } elseif ($payloadLength === 127) {
                if ($length < 10) {
                    return null;
                }
                $payloadLength = unpack('J', substr($buffer, 2, 8))[1];
                $offset = 10;
            }

            $mask = '';
            if ($masked) {
                if ($length < $offset + 4) {
                    return null;
                }
                $mask = substr($buffer, $offset, 4);
                $offset += 4;
            }

            if ($length < $offset + $payloadLength) {
                return null;
            }

            $payload = substr($buffer, $offset, $payloadLength);
            if ($masked) {
                $payload ^= str_repeat($mask, (int) ceil($payloadLength / 4));
            }

            $this->readBuffer = substr($buffer, $offset + $payloadLength);

            return ['opcode' => $opcode, 'payload' => $payload];
        }

        public function close()
        {
            if ($this->connection === null) {
                return;
            }

            if (is_resource($this->connection)) {
                if (count($this->inFlight) > 0) {
                    $this->drainAcks();
                }
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
