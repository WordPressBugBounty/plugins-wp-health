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
        protected $context;

        const READ_CHUNK_SIZE = 1024 * 10;

        public function __construct($params)
        {
            $this->host = $params['host'];
            $this->port = $params['port'];
            $this->key = $params['key'] ?? base64_encode(openssl_random_pseudo_bytes(16));

            $this->wsVersion = $params['wsVersion'] ?? 13;
            $this->transport = $params['transport'] ?? 'tcp';
            $this->timeout = $params['timeout'] ?? 25;
            $this->origin = $params['origin'] ?? $_SERVER['HTTP_HOST'];
            $this->context = $params['context'] ?? null;
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
                'X-Internal-Request: ' . $this->context->getInternalRequest(),
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
                throw new UmbrellaSocketException('connection_closed', 'Connection closed');
            }

            $written = @fwrite($this->connection, $frame);
            if ($written === false) {
                throw new UmbrellaSocketException('write_failed', 'Write failed');
            }

            unset($frame);
        }

        public function sendError(UmbrellaDefaultException $e)
        {
            if($this->connection === null) {
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
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('FILE_CURSOR:' . $data);
        }

        public function sendDatabaseCursor($cursor)
        {
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('DATABASE_CURSOR:' . $data);
        }

        public function sendScanCursor($cursor)
        {
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('SCAN_CURSOR:' . $data);
        }

        public function sendDatabaseDumpCursor($cursor)
        {
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('DATABASE_DUMP_CURSOR:' . $data);
        }

        public function sendFinish()
        {
            if($this->connection === null) {
                return;
            }

            $this->writeFrame('FINISH');
        }

        public function sendLog($message)
        {
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'message' => $message,
            ]);
            $this->writeFrame('LOG:' . $data);
        }

        public function sendPreventMaxExecutionTime($cursor = 0)
        {
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('PREVENT_MAX_EXECUTION_TIME:' . $data);
        }

        public function sendPreventDatabaseMaxExecutionTime($cursor)
        {
            if($this->connection === null) {
                return;
            }

            $data = json_encode([
                'cursor' => $cursor,
            ]);

            $this->writeFrame('PREVENT_DATABASE_MAX_EXECUTION_TIME:' . $data);
        }

        public function isPoolAvailable()
        {
            $this->writeFrame('CHECK_POOL');

            $data = $this->readFrameJson();

            if ($data && $data['type'] === 'POOL_AVAILABLE') {
                return true;
            }

            return false;
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

        public function send($filePath)
        {
            if(!file_exists($filePath)) {
                return;
            }

            $relativePath = substr($filePath, strlen($this->context->getBaseDirectory()) + 1);

            if (!UmbrellaUTF8::seemsUTF8($relativePath)) {
                $relativePath = UmbrellaUTF8::encodeNonUTF8($relativePath);
            }

            $sequence = 0;
            try {
                if (file_exists($filePath)) {
                    $fileHandle = fopen($filePath, 'rb');

                    if($fileHandle === false) {
                        $this->sendLog('Error sending file: ' . $filePath);
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

                    $endOfFileMessage = json_encode([
                        'type' => 'END_FILE',
                        'filename' => $relativePath,
                        'size' => filesize($filePath),
                    ]);

                    $this->writeFrame($endOfFileMessage, false);

                    fclose($fileHandle);

                    return $this->waitForAck($relativePath);
                }
            } catch (\Exception $e) {
                $this->sendLog('Error while sending file: ' . $filePath);
                echo 'Error while sending file: ' . $filePath . "\n";
                return false;
            }
        }

        public function sendFinishDictionary()
        {
            if($this->connection === null) {
                return;
            }

            $this->writeFrame('FINISH_DICTIONARY');
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
            if(substr($jsonPayload, 1, 1) == '{') {
                $jsonPayload = substr($jsonPayload, 1);
            }

            return json_decode($jsonPayload, true);
        }

        public function close()
        {
            if ($this->connection === null) {
                return;
            }

            if(is_resource($this->connection)) {
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
