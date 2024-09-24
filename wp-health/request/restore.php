<?php 

if (!class_exists('UmbrellaDefaultException', false)):
    class UmbrellaDefaultException extends Exception
    {
        protected $error = '';
        protected $errorCode = '';
        protected $internalError = '';

        const ERROR_UNEXPECTED = 'error_unexpected';

        /**
         * @param string $error
         * @param string $code
         */
        public function __construct($error, $code = self::ERROR_UNEXPECTED, $internalError = '')
        {
            $this->message = sprintf('[%s]: %s', $code, $error);
            $this->error = $error;
            $this->errorCode = (string)$code;
            $this->internalError = $internalError;
        }

        public function getError()
        {
            return $this->error;
        }

        public function getErrorCode()
        {
            return $this->errorCode;
        }

        public function getErrorMessage()
        {
            return $this->message;
        }

        public function getErrorStrWithCode()
        {
            switch($this->errorCode) {
                default:
                    if(is_string($this->errorCode)) {
                        return $this->errorCode;
                    }
                    return 'unexpected_error';
            }
        }
    }
endif;

if (!class_exists('UmbrellaException', false)):
    class UmbrellaException extends UmbrellaDefaultException
    {
        public function getErrorStrWithCode()
        {
            switch($this->errorCode) {
                default:
                    if(is_string($this->errorCode)) {
                        return $this->errorCode;
                    }
                    return 'unexpected_error';
            }
        }
    }
endif;

if (!class_exists('UmbrellaSocketException', false)):
    class UmbrellaSocketException extends UmbrellaDefaultException
    {
    
        public function getErrorStrWithCode()
        {
            switch($this->errorCode) {
                case 61:
                    return 'connection_refused';
                default:
                    if(is_string($this->errorCode)) {
                        return $this->errorCode;
                    }
                    return 'unexpected_error';
            }
        }
    }
endif;

if (!class_exists('UmbrellaInternalRequestException', false)):
    class UmbrellaInternalRequestException extends Exception
    {
    }
endif;

if (!class_exists('UmbrellaConnectionInterface', false)):
    interface UmbrellaConnectionInterface
    {
        /**
         * @param string $query
         * @param array  $parameters
         * @param bool   $unbuffered Set to true to not fetch all results into memory and to incrementally read from SQL server.
         *                           See http://php.net/manual/en/mysqlinfo.concepts.buffering.php
         *
         * @return UmbrellaDatabaseStatementInterface
         *
         */
        public function query($query, array $parameters = array(), $unbuffered = false);

        /**
         * No-return-value version of the query() method. Allows adapters
         * to optionally optimize the operation.
         *
         * @param string $query
         *
         */
        public function execute($query);

        /**
         * Escapes string for safe use in statements; quotes are included.
         *
         * @param string $value
         *
         * @return string
         *
         */
        public function escape($value);

        /**
         * Closes the connection.
         */
        public function close();
    }
endif;


if (!class_exists('UmbrellaDatabaseStatementInterface', false)):
    interface UmbrellaDatabaseStatementInterface
    {
        /**
         * @return int
         */
        public function getNumRows();

        /**
         * @return array|null
         *
         * @throws ClonerException
         */
        public function fetch();

        /**
         * @return array|null
         *
         * @throws ClonerException
         */
        public function fetchAll();

        /**
         * @return bool
         */
        public function free();
    }
endif;

if (!class_exists('UmbrellaStatInfo', false)):
    class UmbrellaStatInfo
    {
        // https://unix.superglobalmegacorp.com/Net2/newsrc/sys/stat.h.html
        const S_IFMT = 0170000;   /* type of file */
        const S_IFIFO = 0010000;  /* named pipe (fifo) */
        const S_IFCHR = 0020000;  /* character special */
        const S_IFDIR = 0040000;  /* directory */
        const S_IFBLK = 0060000;  /* block special */
        const S_IFREG = 0100000;  /* regular */
        const S_IFLNK = 0120000;  /* symbolic link */
        const S_IFSOCK = 0140000; /* socket */

        private $stat;
        public $link = '';

        private function __construct(array $stat)
        {
            $this->stat = $stat;
        }

        /**
         * @return bool
         */
        public function isDir()
        {
            return ($this->stat['mode'] & self::S_IFDIR) === self::S_IFDIR;
        }

        public function isLink()
        {
            return ($this->stat['mode'] & self::S_IFLNK) === self::S_IFLNK;
        }

        public function getPermissions()
        {
            return ($this->stat['mode'] & 0777);
        }

        /**
         * @return int
         */
        public function getSize()
        {
            return $this->isDir() ? 0 : $this->stat['size'];
        }

        /**
         * @return int
         */
        public function getMTime()
        {
            return $this->stat['mtime'];
        }

        /**
         * @param array $stat Result of lstat() or stat() function call.
         *
         * @return ClonerStatInfo
         */
        public static function fromArray(array $stat)
        {
            return new self($stat);
        }

        public static function makeEmpty()
        {
            return new self(['size' => 0, 'mode' => 0, 'mtime' => 0]);
        }
    }
endif;

if (!function_exists('getFsStat')):
    function getFsStat($path)
    {
        if (function_exists('lstat')) {
            $stat = @lstat($path);
            if ($stat) {
                $info = UmbrellaStatInfo::fromArray($stat);
                if ($info->isLink()) {
                    $link = readlink($path);
                    if (!is_string($link)) {
                        throw new UmbrellaException('readlink', $path);
                    }
                    $info->link = $link;
                }
                if ($info->getSize() < 0) {
                    throw new UmbrellaException($path);
                }
                return $info;
            }
            $error = error_get_last();
            if (!file_exists($path)) {
                throw new UmbrellaException($path);
            }
            if (empty($error['message']) || strncmp($error['message'], 'lstat(', 0) !== 0) {
                throw new UmbrellaException($path);
            }
        }

        if (function_exists('stat')) {
            $stat = @stat($path);
            if ($stat) {
                $info = UmbrellaStatInfo::fromArray($stat);
                ;
                if (@is_link($path)) {
                    $link = $link = readlink($path);
                    if ($link === false) {
                        throw new UmbrellaException('readlink', $path);
                    }
                    $info->link = $link;
                }
                if ($info->getSize() < 0) {
                    throw new UmbrellaException($path);
                }
                return $info;
            }
            throw new UmbrellaException('stat', $path);
        } else {
            throw new UmbrellaException('lstat', $path);
        }
    }
endif;

if (!class_exists('UmbrellaWebSocket')):
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

if (!class_exists('UmbrellaAbstractProcessRestore')):
    class UmbrellaAbstractProcessRestore
    {
        protected $context;

        protected $socket;

        protected $connection;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;
            $this->connection = $params['connection'] ?? null;
        }
    }
endif;

if (!class_exists('UmbrellaContext')):
    class UmbrellaContext
    {
        const SUFFIX = 'umb_database';

		const RESTORE_SUFFIX = 'umb_restore';

        protected $currentTransfer;

        protected $zipHasBeenReceived;

		protected $zipFilename;


        protected $baseDirectory;

        protected $tables;

        protected $databasePrefix;

        protected $options;

        protected $requestId;

        protected $fileCursor;

        protected $databaseCursor;

        protected $dabataseConnectionInfo;

		protected $retryFromWebsocketServer;

        public function __construct($params)
        {
            $this->baseDirectory = rtrim($params['baseDirectory'], DIRECTORY_SEPARATOR);
            $this->tables = $params['tables'] ?? [];
            $this->requestId = $params['requestId'];
            $this->fileCursor = $params['fileCursor'];
            $this->databaseCursor = $params['databaseCursor'];
            $this->dabataseConnectionInfo = $params['databaseConnectionInfo'];
            $this->retryFromWebsocketServer = $params['retryFromWebsocketServer'] ?? false;

			$this->currentTransfer = $params['currentTransfer'] ?? [
                'filenameSent' => false,
                'chunk' => 0,
                'fileSize' => 0,
                'chunkFactor' => null,
            ];
            $this->zipHasBeenReceived = $params['zipHasBeenReceived'] ?? false;
			$this->zipFilename = $params['zipFilename'] ?? null;
        }

        public function getFileCursor()
        {
            return $this->fileCursor;
        }

        public function getRetryFromWebsocketServer()
        {
            return $this->retryFromWebsocketServer ? 1 : 0;
        }

        public function getDatabaseCursor()
        {
            return $this->databaseCursor;
        }

        public function getBaseDirectory()
        {
            return $this->baseDirectory;
        }

        public function getRequestId()
        {
            return $this->requestId;
        }

        public function getRootDatabaseBackupDirectory()
        {
            return $this->baseDirectory . DIRECTORY_SEPARATOR . self::SUFFIX;
        }

        public function getDbUser()
        {
            return $this->databaseConnectionInfo['user'];
        }

        public function getDbPassword()
        {
            return $this->databaseConnectionInfo['password'];
        }

        public function getDbName()
        {
            return $this->databaseConnectionInfo['name'];
        }

        public function getDbHost()
        {
            return $this->databaseConnectionInfo['host'];
        }

        public function getDbSsl()
        {
            return $this->databaseConnectionInfo['ssl'];
        }

        public function incrementFileCursor()
        {
            $this->fileCursor++;
            return $this;
        }

        public function incrementDatabaseCursor()
        {
            $this->databaseCursor++;
            return $this;
        }



		public function getZipFilename()
		{
			return $this->zipFilename;
		}

        public function getZipHasBeenReceived()
        {
            return $this->zipHasBeenReceived;
        }

        public function getFilenameSent()
        {
            return $this->currentTransfer['filenameSent'];
        }

        public function getChunk()
        {
            return $this->currentTransfer['chunk'];
        }

        public function getChunkFactor()
        {
            return $this->currentTransfer['chunkFactor'];
        }

        public function getFileSize()
        {
            return $this->currentTransfer['fileSize'];
        }

        public function getRootRestoreDirectory()
        {
            return $this->baseDirectory . DIRECTORY_SEPARATOR . self::RESTORE_SUFFIX;
        }

        public function createRestoreDirectoryIfNotExists()
        {
            if (!file_exists($this->getRootRestoreDirectory())) {
                mkdir($this->getRootRestoreDirectory());
            }

            // Write .htaccess with deny all
            $htaccess = $this->getRootRestoreDirectory() . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'deny from all');
            }

            // Write index.php
            $index = $this->getRootRestoreDirectory() . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }

		public function createBackupDirectoryIfNotExists()
        {
            if (!file_exists($this->getRootDatabaseBackupDirectory())) {
                mkdir($this->getRootDatabaseBackupDirectory());
            }

            // Write .htaccess with deny all
            $htaccess = $this->getRootDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'deny from all');
            }

            // Write index.php
            $index = $this->getRootDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
    }
endif;

if (!class_exists('UmbrellaDatabasePreventMaxExecutionTime', false)):
    class UmbrellaDatabasePreventMaxExecutionTime extends Exception
    {
        protected $cursor;

        public function __construct($cursor)
        {
            $this->cursor = $cursor;
            parent::__construct('Prevent max execution time');
        }

        public function getCursor()
        {
            return $this->cursor;
        }
    }
endif;

if (!class_exists('UmbrellaPreventMaxExecutionTime', false)):
    class UmbrellaPreventMaxExecutionTime extends Exception
    {
        protected $cursor;

        public function __construct($cursor = 0)
        {
            $this->cursor = $cursor;
            parent::__construct('Prevent max execution time');
        }

        public function getCursor()
        {
            return $this->cursor;
        }
    }
endif;

if(!class_exists('UmbrellaSqlInstruction')):
    class UmbrellaSqlInstruction
    {
        public static function createSelectQuery($tableName, array $columns)
        {
            $select = 'SELECT ';
            foreach ($columns as $i => $column) {
                if ($i > 0) {
                    $select .= ', ';
                }
                switch ($column->type) {
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'blob':
                    case 'longblob':
                    case 'binary':
                    case 'varbinary':
                        $select .= "HEX(`$column->name`)";
                        break;
                    default:
                        $select .= "`$column->name`";
                        break;
                }
            }
            $select .= " FROM `$tableName`;";

            return $select;
        }

        public static function createInsertQuery(UmbrellaConnectionInterface $connection, $tableName, array $columns, array $row)
        {
            $insert = "INSERT INTO `$tableName` VALUES (";
            $i = 0;
            foreach ($row as $value) {
                $column = $columns[$i];
                if ($i > 0) {
                    $insert .= ',';
                }
                $i++;
                if ($value === null) {
                    $insert .= 'null';
                    continue;
                }
                switch ($column->type) {
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'int':
                    case 'bigint':
                    case 'decimal':
                    case 'float':
                    case 'double':
                        $insert .= $value;
                        break;
                    case 'tinyblob':
                    case 'mediumblob':
                    case 'blob':
                    case 'longblob':
                    case 'binary':
                    case 'varbinary':
                        if (strlen($value) === 0) {
                            $insert .= "''";
                        } else {
                            $insert .= "0x$value";
                        }
                        break;
                    case 'bit':
                        $insert .= $value ? "b'1'" : "b'0'";
                        break;
                    default:
                        $insert .= $connection->escape($value);
                        break;
                }
            }
            $insert .= ");\n";

            return $insert;
        }

        public static function dumpTable(UmbrellaConnectionInterface $connection, $table, UmbrellaFileHandle $fileHandle)
        {
            $tableName = $table['name'];
            $noData = $table['noData'];
            $columns = $table['columns'];
            $written = 0;
            $result = $connection->query("SHOW CREATE TABLE `$tableName`")->fetch();
            $createTable = $result['Create Table'];
            if (empty($createTable)) {
                throw new UmbrellaException(sprintf('SHOW CREATE TABLE did not return expected result for table %s', $tableName), 'no_create_table');
            }

            $time = date('c');
            $fetchAllQuery = self::createSelectQuery($tableName, $columns);
            $haltCompiler = '#<?php die(); ?>';
            $dumper = get_class($connection);
            $phpVersion = phpversion();
            $header = <<<SQL
    $haltCompiler
    -- Umbrella backup format
    -- Generated at: $time by $dumper; PHP v$phpVersion
    -- Selected via: $fetchAllQuery

    /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
    /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
    /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
    /*!40101 SET NAMES utf8 */;
    /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
    /*!40103 SET TIME_ZONE='+00:00' */;
    /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
    /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
    /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
    /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

    DROP TABLE IF EXISTS `$tableName`;

    /*!40101 SET @saved_cs_client     = @@character_set_client */;
    /*!40101 SET character_set_client = utf8 */;

    $createTable;

    /*!40101 SET character_set_client = @saved_cs_client */;

    SQL;
            if (!$noData) {
                $header .= <<<SQL
    LOCK TABLES `$tableName` WRITE;
    /*!40000 ALTER TABLE `$tableName` DISABLE KEYS */;

    SQL;
            }
            $fileHandle->write($header);
            $written += strlen($header);

            if (!$noData) {
                $flushSize = 8 << 20;
                $buf = '';
                $fetchAll = $connection->query($fetchAllQuery, [], true);
                while ($row = $fetchAll->fetch()) {
                    $buf .= self::createInsertQuery($connection, $tableName, $columns, $row);
                    if (strlen($buf) < $flushSize) {
                        continue;
                    }
                    $fileHandle->write($buf);
                    $written += strlen($buf);
                    $buf = '';
                }
                if (strlen($buf)) {
                    $fileHandle->write($buf);
                    $written += strlen($buf);
                    unset($buf);
                }
                $fetchAll->free();
            }

            $footer = <<<SQL

    /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
    /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
    /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
    /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
    /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
    /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
    /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
    /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

    SQL;
            if (!$noData) {
                $footer = <<<SQL

    /*!40000 ALTER TABLE `$tableName` ENABLE KEYS */;
    UNLOCK TABLES;
    SQL
                    . $footer;
            }
            $fileHandle->write($footer);
            $written += strlen($footer);

            return $written;
        }
    }

endif;

if(!class_exists('UmbrellaMySQLiConnection')):
    class UmbrellaMySQLiConnection implements UmbrellaConnectionInterface
    {
        protected $connection;

        protected $configuration;

        public function getConfiguration()
        {
            return $this->configuration;
        }

        /**
         * @param UmbrellaDatabaseConfiguration
         */
        public function __construct(UmbrellaDatabaseConfiguration $configuration)
        {
            if (!extension_loaded('mysqli')) {
                throw new UmbrellaException('Mysqli extension is not enabled.', 'mysqli_disabled');
            }

            $this->configuration = $configuration;

            mysqli_report(MYSQLI_REPORT_OFF);

            // Silence possible warnings thrown by mysqli
            // e.g. Warning: mysqli::mysqli(): Headers and client library minor version mismatch. Headers:50540 Library:50623

            $flag = 0;
            if ($configuration->useSSL) {
                $flag = MYSQLI_CLIENT_SSL;
            }

            $this->connection = mysqli_init();
            $success = $this->connection->real_connect(
                $configuration->getHostname(),
                $configuration->user,
                $configuration->password,
                $configuration->name,
                $configuration->getPort(),
                null,
                $flag
            );

            if ($success) {
                $this->connection->set_charset(UmbrellaDatabaseFunction::getDatabaseCharset($this));
                return;
            }

            if ($this->connection->connect_errno === 2002 && strtolower($configuration->getHostname()) === 'localhost') {
                // Attempt to recover from "[2002] No such file or directory" error.
                $this->connection = mysqli_init();
                $success = $this->connection->real_connect(
                    '127.0.0.1',
                    $configuration->user,
                    $configuration->password,
                    $configuration->name,
                    $configuration->getPort(),
                    null,
                    $flag
                );
            }

            if (!$success) {
                // Note: The error message is not always accurate, so we don't use it.
                // if(strpos($this->connection->connect_error, 'require_secure_transport') !== false) {
                $this->connection = mysqli_init();
                $success = $this->connection->real_connect(
                    $configuration->getHostname(),
                    $configuration->user,
                    $configuration->password,
                    $configuration->name,
                    $configuration->getPort(),
                    null,
                    MYSQLI_CLIENT_SSL
                );
                // }
            }

            if(!$success) {
                throw new UmbrellaException($this->connection->connect_error, 'db_connect_error_mysqli', $this->connection->connect_errno);
            }

            $this->connection->set_charset(UmbrellaDatabaseFunction::getDatabaseCharset($this));
        }

        public function query($query, array $parameters = [], $unbuffered = false)
        {
            $query = UmbrellaDatabaseFunction::bindQueryParams($this, $query, $parameters);

            $resultMode = $unbuffered ? MYSQLI_USE_RESULT : 0;
            $result = $this->connection->query($query, $resultMode);

            // There are certain warnings that result in $result being false, eg. PHP Warning:  mysqli::query(): Empty query,
            // but the error number is 0.
            if ($result === false && $this->connection->errno !== 0) {
                throw new UmbrellaException($this->connection->error, 'db_query_error', $this->connection->errno);
            }

            return new UmbrellaMySQLiStatement($this->connection, $result);
        }

        public function execute($query)
        {
            $this->query($query);
        }

        public function escape($value)
        {
            return $value === null ? 'null' : "'" . $this->connection->real_escape_string($value) . "'";
        }

        public function close()
        {
            if (empty($this->connection)) {
                return;
            }
            $this->connection->close();
            $this->connection = null;
        }
    }

endif;

if(!class_exists('UmbrellaMySQLConnection')):
    class UmbrellaMySQLConnection implements UmbrellaConnectionInterface
    {
        protected $connection;

        protected $configuration;

        public function getConfiguration()
        {
            return $this->configuration;
        }

        /**
         * @param UmbrellaDatabaseConfiguration $conf
         *
         * @throws Exception
         */
        public function __construct(UmbrellaDatabaseConfiguration $configuration)
        {
            if (!extension_loaded('mysql')) {
                throw new UmbrellaException('Mysql extension is not loaded.', 'mysql_disabled');
            }

            $this->configuration = $configuration;

            $flag = 0;
            if ($this->configuration->useSSL) {
                $flag = MYSQL_CLIENT_SSL;
            }

            $this->connection = @mysql_connect($this->configuration->host, $this->configuration->user, $this->configuration->password, false, $flag);
            if (!is_resource($this->connection)) {
                // Attempt to recover from "[2002] No such file or directory" error.
                $errno = mysql_errno();
                if ($errno !== 2002 || strtolower($this->configuration->getHostname()) !== 'localhost' || !is_resource($this->connection = @mysql_connect('127.0.0.1', $this->configuration->user, $this->configuration->password, false, $flag))) {
                    throw new UmbrellaException(mysql_error(), 'db_connect_error_mysql', (string)$errno);
                }
            }
            if (mysql_select_db($this->configuration->name, $this->connection) === false) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_connect_error_mysql', (string)mysql_errno($this->connection));
            }
            if (!@mysql_set_charset(cloner_db_charset($this), $this->connection)) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_connect_error_mysql', (string)mysql_errno($this->connection));
            }
        }

        public function query($query, array $parameters = [], $unbuffered = false)
        {
            $query = UmbrellaDatabaseFunction::bindQueryParams($this, $query, $parameters);

            if ($unbuffered) {
                $result = mysql_unbuffered_query($query, $this->connection);
            } else {
                $result = mysql_query($query, $this->connection);
            }

            if ($result === false) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_query_error', (string)mysql_errno($this->connection));
            } elseif ($result === true) {
                // This is one of INSERT, UPDATE, DELETE, DROP statements.
                return new ClonerMySQLStmt($this->connection, null);
            } else {
                // This is one of SELECT, SHOW, DESCRIBE, EXPLAIN statements.
                return new ClonerMySQLStmt($this->connection, $result);
            }
        }

        public function execute($query)
        {
            $this->query($query);
        }

        public function escape($value)
        {
            return $value === null ? 'null' : "'" . mysql_real_escape_string($value, $this->connection) . "'";
        }

        public function close()
        {
            if (empty($this->connection)) {
                return;
            }
            mysql_close($this->connection);
            $this->connection = null;
        }
    }

endif;

if(!class_exists('UmbrellaPDOConnection')):
    class UmbrellaPDOConnection implements UmbrellaConnectionInterface
    {
        protected $connection;
        protected $unbuffered = false;

        public function getConfiguration()
        {
            return $this->configuration;
        }

        /**
         * @param bool $attEmulatePrepares
         */
        public function setAttEmulatePrepares($attEmulatePrepares)
        {
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $attEmulatePrepares);
        }

        /**
         * @param UmbrellaDatabaseConfiguration $configuration
         */
        public function __construct(UmbrellaDatabaseConfiguration $configuration)
        {
            $this->configuration = $configuration;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            if ($configuration->useSSL) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = true;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                $this->connection = new PDO(self::getDsn($configuration), $configuration->user, $configuration->password, $options);
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 2002 && strtolower($configuration->getHostname()) === 'localhost') {
                    try {
                        $configuration = clone $configuration;
                        $configuration->host = '127.0.0.1';
                        $this->connection = new PDO(self::getDsn($configuration), $configuration->user, $configuration->password, $options);
                    } catch (PDOException $e2) {
                        throw new UmbrellaException($e->getMessage(), 'db_connect_error_pdo', (string)$e2->getCode());
                    }
                } else {
                    throw new UmbrellaException($e->getMessage(), 'db_connect_error_pdo', (string)$e->getCode());
                }
            }

            // ATTR_EMULATE_PREPARES is not necessary for newer mysql versions
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, version_compare($this->connection->getAttribute(PDO::ATTR_SERVER_VERSION), '5.1.17', '<'));
            $this->connection->exec(sprintf('SET NAMES %s', UmbrellaDatabaseFunction::getDatabaseCharset($this)));
        }

        public function query($query, array $parameters = [], $unbuffered = false)
        {
            if ($this->unbuffered !== $unbuffered) {
                $this->unbuffered = $unbuffered;
                $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !$unbuffered);
            }

            try {
                $statement = $this->connection->prepare($query);
                $statement->execute($parameters);
                return new UmbrellaPDOStatement($statement);
            } catch (PDOException $e) {
                $internalErrorCode = isset($e->errorInfo[1]) ? (string)$e->errorInfo[1] : '';
                throw new UmbrellaException($e->getMessage(), 'db_query_error', $internalErrorCode);
            }
        }

        public function execute($query)
        {
            try {
                $this->connection->exec($query);
            } catch (PDOException $e) {
                $internalErrorCode = isset($e->errorInfo[1]) ? (string)$e->errorInfo[1] : '';
                throw new UmbrellaException($e->getMessage(), 'db_query_error', $internalErrorCode);
            }
        }

        public function escape($value)
        {
            return $value === null ? 'null' : $this->connection->quote($value);
        }

        public function close()
        {
            $this->connection = null;
        }

        public static function getDsn(UmbrellaDatabaseConfiguration $configuration)
        {
            $pdoParameters = [
                'dbname' => $configuration->name,
                'charset' => 'utf8',
            ];
            $socket = $configuration->getSocket();
            if ($socket !== '') {
                $pdoParameters['host'] = $configuration->getHostname();
                $pdoParameters['unix_socket'] = $socket;
            } else {
                $pdoParameters['host'] = $configuration->getHostname();
                $pdoParameters['port'] = $configuration->getPort();
            }
            $parameters = [];
            foreach ($pdoParameters as $name => $value) {
                $parameters[] = $name . '=' . $value;
            }
            $dsn = sprintf('mysql:%s', implode(';', $parameters));
            return $dsn;
        }
    }

endif;

if (!class_exists('UmbrellaMySQLiStatement', false)):
    class UmbrellaMySQLiStatement implements UmbrellaDatabaseStatementInterface
    {
        protected $connection;
        protected $result;

        /**
         * @param mysqli_driver      $result
         * @param mysqli_result|bool $result
         */
        public function __construct($connection, $result)
        {
            $this->connection   = $connection;
            $this->result = $result;
        }

        /**
         * @return array|null
         */
        public function fetch()
        {
            if (($this->result === false || $this->result === null) && $this->connection->errno) {
                throw new UmbrellaException($this->connection->error, 'db_query_error', $this->connection->errno);
            } elseif (!$this->result) {
                throw new UmbrellaException("Only read-only queries can yield results.", 'db_query_error');
            }
            $result = $this->result->fetch_assoc();
            if (($result === false || $result === null) && $this->connection->errno) {
                throw new UmbrellaException($this->connection->error, 'db_query_error', $this->connection->errno);
            }
            return $result;
        }

        /**
         * @return array|null
         */
        public function fetchAll()
        {
            $rows = [];
            while ($row = $this->fetch()) {
                $rows[] = $row;
            }
            return $rows;
        }

        /**
         * @return int
         */
        public function getNumRows()
        {
            if (is_bool($this->result)) {
                return 0;
            }
            return $this->result->num_rows;
        }

        /**
         * @return bool
         */
        public function free()
        {
            if (is_bool($this->result)) {
                return false;
            }
            mysqli_free_result($this->result);
            return true;
        }
    }
endif;

if (!class_exists('UmbrellaPDOStatement', false)):
    class UmbrellaPDOStatement implements UmbrellaDatabaseStatementInterface
    {

        protected $statement;


        public function __construct(PDOStatement $statement)
        {
            $this->statement = $statement;
        }

        public function fetch()
        {
            try {
                return $this->statement->fetch();
            } catch (PDOException $e) {
                $internalErrorCode = isset($e->errorInfo[1]) ? (string)$e->errorInfo[1] : '';
                throw new UmbrellaException($e->getMessage(), 'db_query_error', $internalErrorCode);
            }
        }

        public function fetchAll()
        {
            return $this->statement->fetchAll();
        }

        public function getNumRows()
        {
            return $this->statement->rowCount();
        }

        public function free()
        {
            return $this->statement->closeCursor();
        }
    }
endif;

if (!class_exists('UmbrellaMySQLStatement', false)):
    class UmbrellaMySQLStatement implements UmbrellaDatabaseStatementInterface
    {
        protected $connection;
        protected $result;

        /**
         * @param resource      $conn
         * @param resource|null $result
         *
         * @throws Exception
         */
        public function __construct($connection, $result = null)
        {
            $this->connection   = $connection;
            $this->result = $result;
        }

        public function fetch()
        {
            if ($this->result === false && mysql_errno($this->connection)) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_query_error', mysql_errno($this->connection));
            } elseif (!is_resource($this->result)) {
                throw new UmbrellaException("Only read-only queries can yield results.", 'db_query_error');
            }
            $result = @mysql_fetch_assoc($this->result);
            if ($result === false && mysql_errno($this->connection)) {
                throw new UmbrellaException(mysql_error($this->connection), 'db_query_error', mysql_errno($this->connection));
            }
            return $result;
        }

        public function fetchAll()
        {
            $rows = array();
            while ($row = $this->fetch()) {
                $rows[] = $row;
            }
            return $rows;
        }

        public function getNumRows()
        {
            return mysql_num_rows($this->result);
        }

        public function free()
        {
            if (!is_resource($this->result)) {
                return true;
            }
            return mysql_free_result($this->result);
        }
    }
endif;

if(!class_exists('UmbrellaDatabaseFunction')):
    abstract class UmbrellaDatabaseFunction
    {
        protected static $connection;

        public static function getConnection($params)
        {
            if(null !== self::$connection) {
                return self::$connection;
            }

            if (extension_loaded('mysqli')) {
                self::$connection = new UmbrellaMySQLiConnection($params);
            } elseif (extension_loaded('pdo_mysql')) {
                self::$connection = new UmbrellaPDOConnection($params);
            } elseif (extension_loaded('mysql')) {
                self::$connection = new UmbrellaMySQLConnection($params);
            } else {
                throw new UmbrellaException('No drivers available for php mysql connection.', 'no_db_drivers');
            }

            return self::$connection;
        }

        public static function getTableSchemaOnly($tableName, $prefix = 'wp_')
        {
            $ignored = array_map(function ($table) use ($prefix) {
                return $prefix . $table;
            }, [
                'wysija_user_history',
                '_wsd_plugin_alerts',
                '_wsd_plugin_live_traffic',
                'adrotate_tracker',
                'aiowps_events',
                'ak_404_log',
                'bad_behavior',
                'cn_track_post',
                'nginxchampuru',
                'popover_ip_cache',
                'redirection_404',
                'spynot_systems_log',
                'statify',
                'statistics_useronline',
                'tcb_api_error_log',
                'useronline',
                'wbz404_logs',
                'wfHits',
                'wfLeechers',
                'who_is_online',
                'simple_history',
                'simple_history_contexts',
                'wfHoover',
                'et_bloom_stats',
                'itsec_log',
                'itsec_logs',
                'itsec_temp',
                'cpd_counter',
                'session',
                'wpaas_activity_log',
                'umbrella_log',
                'woocommerce_log',
                'fsmpt_email_logs',
                'email_log',
                'amelia_notifications_log',
                'bookly_log',
                'actionscheduler_actions',
                'actionscheduler_logs'
            ]);

            if(in_array($tableName, $ignored)) {
                return true;
            }

            return false;
        }

        public static function getDatabaseInformation(UmbrellaConnectionInterface $connection)
        {
            $info = [
                'collation' => [],
                'charset' => [],
            ];

            $list = $connection->query('SHOW COLLATION')->fetchAll();
            foreach ($list as $row) {
                $info['collation'][$row['Collation']] = true;
                $info['charset'][$row['Charset']] = true;
            }
            return $info;
        }

        public static function getListTables(UmbrellaConnectionInterface $connection, UmbrellaContext $context)
        {
            $tableNames = $context->getTables();

            if(empty($tableNames)) {
                return [];
            }

            $result = [];

            $tables = $connection->query(
                'SELECT `table_name` AS `name`, `data_length` AS `dataSize`
                            FROM information_schema.TABLES
                            WHERE table_schema = :db_name AND table_type = :table_type AND engine IS NOT NULL',
                [
                    'db_name' => $connection->getConfiguration()->name,
                    'table_type' => 'BASE TABLE', // as opposed to VIEW
                ]
            )->fetchAll();

            foreach ($tables as $table) {
                if (!in_array($table['name'], $tableNames, true)) {
                    continue;
                }

                $result[] = [
                    'name' => $table['name'],
                    'type' => UmbrellaTableType::REGULAR,
                    'dataSize' => (int)$table['dataSize'],
                    'noData' => self::getTableSchemaOnly($table['name'], $context->getDatabasePrefix()),
                ];
            }

            return $result;
        }

        public static function getTableColumns(UmbrellaConnectionInterface $connection, $table)
        {
            $columnList = $connection->query("SHOW COLUMNS IN `$table`")->fetchAll();

            $columns = [];
            foreach ($columnList as $columnData) {
                $column = new UmbrellaDatabaseColumn();
                $column->name = $columnData['Field'];
                $type = strtolower($columnData['Type']);
                if (($openParen = strpos($type, '(')) !== false) {
                    // Transform "int(11)" to "int", etc.
                    $type = substr($type, 0, $openParen);
                }
                $column->type = $type;
                $columns[] = $column;

                if ($connection instanceof UmbrellaPDOConnection && strpos($column->name, '?') !== false) {
                    $connection->setAttEmulatePrepares(false);
                }
            }

            return $columns;
        }

        public static function getDatabaseCharset(UmbrellaConnectionInterface $connection)
        {
            $info = self::getDatabaseInformation($connection);
            $try = 'utf8mb4';
            foreach ($info['charset'] as $charset => $true) {
                if (strpos($charset, $try) === false) {
                    continue;
                }
                return $try;
            }
            return 'utf8';
        }

        public static function bindQueryParams(UmbrellaConnectionInterface $connection, $query, array $params)
        {
            if (count($params) === 0) {
                return $query;
            }
            $replacements = [];
            foreach ($params as $name => $value) {
                $replacements[":$name"] = $connection->escape($value);
            }
            return strtr($query, $replacements);
        }
    }

endif;

if (!class_exists('UmbrellaDatabaseColumn', false)):
    class UmbrellaDatabaseColumn
    {
        public $name = '';
        public $type = '';
    
        public static function fromArray(array $data)
        {
            $column = new self;
            if (isset($data['name'])) {
                $column->name = $data['name'];
            }
            if (isset($data['type'])) {
                $column->type = $data['type'];
            }
            return $column;
        }
    }
endif;
if (!class_exists('UmbrellaTable', false)):
    class UmbrellaTable
    {
        public $name = '';
        public $type = 0;
        public $size = 0;
        public $dataSize = 0;
        public $storage = '';
        public $done = false;
        public $listed = false;
        /** @var UmbrellaColumn[] */
        public $columns = array();
        public $path = '';
        public $noData = false;
        public $hash = '';
        public $source = '';

        public static function fromArray(array $data)
        {
            $table = new self;
            if (isset($data['name'])) {
                $table->name = $data['name'];
            }
            if (isset($data['type'])) {
                $table->type = $data['type'];
            }
            if (isset($data['size'])) {
                $table->size = $data['size'];
            }
            if (isset($data['dataSize'])) {
                $table->dataSize = $data['dataSize'];
            }
            if (isset($data['storage'])) {
                $table->storage = $data['storage'];
            }
            if (isset($data['done'])) {
                $table->done = $data['done'];
            }
            if (isset($data['listed'])) {
                $table->listed = $data['listed'];
            }
            if (isset($data['columns'])) {
                foreach ($data['columns'] as $column) {
                    $table->columns[] = UmbrellaColumn::fromArray($column);
                }
            }
            if (isset($data['path'])) {
                $table->path = $data['path'];
            }
            if (isset($data['noData'])) {
                $table->noData = $data['noData'];
            }
            if (isset($data['source'])) {
                $table->source = $data['source'];
            }
            if (isset($data['hash'])) {
                $table->hash = $data['hash'];
            }
            return $table;
        }
    }
endif;

if (!class_exists('UmbrellaTableType', false)):
    class UmbrellaTableType
    {
        const REGULAR = 0;
        const VIEW = 1;
        const PROCEDURE = 2;
        const FUNC = 3;
    }
endif;

if (!class_exists('UmbrellaDatabaseConfiguration', false)):
    class UmbrellaDatabaseConfiguration
    {
        public $user = '';
        public $password = '';
        /** @var string https://codex.wordpress.org/Editing_wp-config.php#Possible_DB_HOST_values */
        public $host = '';
        public $name = '';
        public $useSSL = false;

        public function __construct($user, $password, $host, $name, $useSSL = false)
        {
            $this->user = $user;
            $this->password = $password;
            $this->host = $host;
            $this->name = $name;
            $this->useSSL = $useSSL;
        }

        public static function fromArray($info)
        {
            if (empty($info)) {
                return self::createEmpty();
            } elseif ($info instanceof self) {
                return $info;
            }
            return new self(
                $info['db_user'],
                $info['db_password'],
                $info['db_host'],
                $info['db_name'],
                $info['db_ssl']
            );
        }

        public static function createEmpty()
        {
            return new self('', '', '', '');
        }

        public function getHostname()
        {
            $parts = explode(':', $this->host, 2);
            if ($parts[0] === '') {
                return 'localhost';
            }
            return $parts[0];
        }

        public function getPort()
        {
            if (strpos($this->host, '/') !== false) {
                return 0;
            }
            $parts = explode(':', $this->host, 2);
            if (count($parts) === 2) {
                return (int)$parts[1];
            }
            return 0;
        }

        public function getSocket()
        {
            return self::getSocketPath($this->host);
        }

        public function setUseSSL($ssl)
        {
            $this->useSSL = $ssl;
            return $this;
        }

        public function toArray()
        {
            return [
                'db_user' => $this->user,
                'db_password' => $this->password,
                'db_name' => $this->name,
                'db_host' => $this->host,
                'db_ssl' => $this->useSSL,
            ];
        }

        protected static function getSocketPath($host)
        {
            if (strpos($host, '/') === false) {
                return '';
            }
            $parts = explode(':', $host, 2);
            if (count($parts) === 2) {
                return $parts[1];
            }
            return $parts[0];
        }
    }
endif;

if (!class_exists('UmbrellaImportDump', false)):
    class UmbrellaImportDump
    {
        public $size = 0;
        public $processed = 0;
        public $path = '';
        public $encoding = '';
        public $source = '';
        public $type = 0;

        public function __construct($size, $processed, $path, $encoding, $source, $type)
        {
            $this->size = (int)$size;
            $this->processed = (int)$processed;
            $this->path = (string)$path;
            $this->encoding = (string)$encoding;
            $this->source = (string)$source;
            $this->type = (int)$type;
        }
    }
endif;

if(!class_exists('DatabaseImportTable')):
    class DatabaseImportTable
    {
        public function filterStatement($statement, array $filters)
        {
            foreach ($filters as $filter) {
                $statement = $filter->filter($statement);
            }
            return $statement;
        }

        public function import(UmbrellaConnectionInterface $connection, UmbrellaImportState $state, $maxCount = 10000, $filters = [])
        {
            clearstatcache();
            $maxPacket = $realMaxPacket = 0;

            if (is_array($maxPacketResult = $connection->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->fetch())) {
                $maxPacket = $realMaxPacket = (int)end($maxPacketResult);
            }
            if (!$maxPacket) {
                $maxPacket = 128 << 10;
            } elseif ($maxPacket > 512 << 10) {
                $maxPacket = 512 << 10;
            }

            $shifts = 0;

            while (($dump = $state->next()) !== null) {
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

                $charsetFixer = new UmbrellaCharsetFixer($connection);
                while (strlen($statements = $scanner->scan($maxCount, $maxPacket))) {
                    if ($realMaxPacket && strlen($statements) + 20 > $realMaxPacket) {
                        throw new UmbrellaException(sprintf("A query in the backup (%d bytes) is too big for the SQL server to process (max %d bytes); please set the server's variable 'max_allowed_packet' to at least %d and retry the process", strlen($statements), $realMaxPacket, strlen($statements) + 20), 'db_max_packet_size_reached', strlen($statements));
                    }
                    if (preg_match('{^\s*(?:/\\*!\d+\s*)?set\s+(?:character_set_client\s*=|names\s+)}i', $statements)) {
                        // Skip all the /*!40101 SET character_set_client=*** */; statements.
                        continue;
                    }

                    try {
                        $statements = $this->filterStatement($statements, $filters);
                        $connection->execute($statements);
                        $shifts = 0;

                        if (strncmp($statements, 'DROP TABLE IF EXISTS ', 21) === 0) {
                            $state->pushNextToEnd();
                            // We just dropped a table; switch to next file if available.
                            // This way we will drop all tables before importing new data.
                            // That helps with foreign key constraints.
                            break;
                        }
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
                                    continue 3;
                                }
                                throw new UmbrellaException(cloner_format_query_error($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                            case '1115':
                            case '1273':
                                $newStatements = preg_replace_callback('{utf8mb4[a-z0-9_]*}', [$charsetFixer, 'replaceCharsetOrCollation'], $statements, -1, $count);
                                if ($count) {
                                    try {
                                        $connection->execute($newStatements);
                                        break;
                                    } catch (UmbrellaException $e2) {
                                    }
                                }
                                throw new UmbrellaException(cloner_format_query_error($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
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
                                        if ($realMaxPacket && (strlen($statements) * 1.2) > $realMaxPacket) {
                                            // We are certain that the packet size is too big.
                                            $connection->execute(sprintf('SET GLOBAL max_allowed_packet=%d', strlen($statements) + 1024 * 1024));
                                        }
                                        $connection->execute($statements);
                                        break 2;
                                    } catch (Exception $e2) {
                                        trigger_error(sprintf('Could not increase max_allowed_packet: %s for file %s at offset %d', $e2->getMessage(), $dump->path, $scanner->tell()));
                                    }
                                }
                                // We aren't certain of what happened here. Maybe reconnect once?
                                throw new UmbrellaException(cloner_format_query_error($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                            case '1231':
                                // Ignore errors like this:
                                // SQLSTATE[42000]: Syntax error or access violation: 1231 Variable 'character_set_client' can't be set to the value of 'NULL'
                                // We don't save the SQL variable state between imports since we only care about the relevant ones (encoding, timezone).
                                break;
                                //case 1065:
                                // Ignore error "[1065] Query was empty"
                                //  break;
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
                                        break;
                                    } catch (Exception $e2) {
                                        trigger_error($e2->getMessage());
                                    }
                                }
                                throw new UmbrellaException(cloner_format_query_error($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
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
                                    break;
                                }
                                throw new UmbrellaException(cloner_format_query_error($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                            case '1298':
                                // 1298 Unknown or incorrect time zone
                                break;
                            case '1419':
                                // Triggers require super-user permissions.
                                //
                                //   Query:
                                //   /*!50003 CREATE*/ /*!50003 TRIGGER wp_hmenu_mega_list BEFORE UPDATE ON wp_hmenu_mega_list FOR EACH ROW SET NEW.lastModified = NOW() */;
                                //
                                //   Error:
                                //   SQLSTATE[HY000]: General error: 1419 You do not have the SUPER privilege and binary logging is enabled (you *might* want to use the less safe log_bin_trust_function_creators variable)
                                $state->skipStatement($statements);
                                break;
                            case '1227':
                                if (strncmp($statements, 'SET @@SESSION.', 14) === 0 || strncmp($statements, 'SET @@GLOBAL.', 13) === 0) {
                                    // SET @@SESSION.SQL_LOG_BIN= 0;
                                    // SET @@GLOBAL.GTID_PURGED='';
                                    break;
                                }
                                // Remove strings like DEFINER=`user`@`localhost`, because they generate errors like this:
                                // "[1227] Access denied; you need (at least one of) the SUPER privilege(s) for this operation"
                                // Example of a problematic query:
                                //
                                //  /*!50003 CREATE*/ /*!50017 DEFINER=`user`@`localhost`*/ /*!50003 TRIGGER `wp_hlogin_default_storage_table` BEFORE UPDATE ON `wp_hlogin_default_storage_table`
                                $newStatements = preg_replace('{(/\*!\d+) DEFINER=`[^`]+`@`[^`]+`(\*/ )}', '', $statements, 1, $count);
                                if ($count) {
                                    try {
                                        $connection->execute($newStatements);
                                        break;
                                    } catch (UmbrellaException $e) {
                                    }
                                }

                                if ($dump->type === UmbrellaTableType::PROCEDURE || $dump->type === UmbrellaTableType::FUNC || $dump->type === UmbrellaTableType::VIEW) {
                                    // Try for procedure, function or view to remove strings like DEFINER=`user`@`localhost`
                                    // If it fails just continue, we don't want to break due to problem with functions, procedures or views
                                    $newStatements = preg_replace('{DEFINER=`[^`]+`@`[^`]+`}', '', $statements, 1, $count);
                                    if ($count) {
                                        try {
                                            $connection->execute($newStatements);
                                        } catch (UmbrellaException $e) {
                                            $state->skipStatement($statements);
                                        }
                                    }

                                    break;
                                }

                                throw new UmbrellaException(cloner_format_query_error($e->getMessage(), $statements, $dump->path, $dump->processed, $scanner->tell(), $dump->size), 'db_query_error', $e->getInternalError());
                            case '3167':
                                if (strpos($statements, '@is_rocksdb_supported') !== false) {
                                    // RocksDB support handling for the following case:
                                    //
                                    // /*!50112 SELECT COUNT(*) INTO @is_rocksdb_supported FROM INFORMATION_SCHEMA.SESSION_VARIABLES WHERE VARIABLE_NAME='rocksdb_bulk_load' */;
                                    // /*!50112 SET @save_old_rocksdb_bulk_load = IF (@is_rocksdb_supported, 'SET @old_rocksdb_bulk_load = @@rocksdb_bulk_load', 'SET @dummy_old_rocksdb_bulk_load = 0') */;
                                    // /*!50112 PREPARE s FROM @save_old_rocksdb_bulk_load */;
                                    // /*!50112 EXECUTE s */;
                                    // /*!50112 SET @enable_bulk_load = IF (@is_rocksdb_supported, 'SET SESSION rocksdb_bulk_load = 1', 'SET @dummy_rocksdb_bulk_load = 0') */;
                                    // /*!50112 PREPARE s FROM @enable_bulk_load */;
                                    // /*!50112 EXECUTE s */;
                                    // /*!50112 DEALLOCATE PREPARE s */;
                                    // ... table creation and insert statements ...
                                    // /*!50112 SET @disable_bulk_load = IF (@is_rocksdb_supported, 'SET SESSION rocksdb_bulk_load = @old_rocksdb_bulk_load', 'SET @dummy_rocksdb_bulk_load = 0') */;
                                    // /*!50112 PREPARE s FROM @disable_bulk_load */;
                                    // /*!50112 EXECUTE s */;
                                    // /*!50112 DEALLOCATE PREPARE s */;
                                    //
                                    // Error on the first statement:
                                    //   #3167 - The 'INFORMATION_SCHEMA.SESSION_VARIABLES' feature is disabled; see the documentation for 'show_compatibility_56'
                                    try {
                                        $connection->execute('SET @is_rocksdb_supported = 0');
                                    } catch (UmbrellaException $e2) {
                                        throw new UmbrellaException('Could not recover from RocksDB support patch: ' . $e2->getMessage());
                                    }
                                    break;
                                }
                                throw new UmbrellaException($e->getMessage(), 'db_query_error');
                            default:
                                if ($dump->type !== UmbrellaTableType::REGULAR) {
                                    $state->skipStatement($statements);
                                    break;
                                }
                                throw new UmbrellaException($e->getMessage(), 'db_query_error');
                        }
                    } catch (Exception $e) {
                        error_log($e->getMessage());
                    }

                    $dump->processed = $scanner->tell();
                    // if ($deadline->done()) {
                    // If there are any locked tables we might hang forever with the next query, unlock them.
                    // $connection->execute('UNLOCK TABLES');
                    // We're cutting the import here - remember the encoding!!!
                    // $charset = $connection->query("SHOW VARIABLES LIKE 'character_set_client'")->fetch();
                    // $dump->encoding = (string)end($charset);
                    // break 2;
                    // }
                }

                $dump->processed = $scanner->tell();
                $scanner->close();
            }

            return $state;
        }
    }
endif;

if (!class_exists('UmbrellaCharsetFixer', false)):
    class UmbrellaCharsetFixer
    {
        protected $connection;
        protected $info;

        public function __construct(UmbrellaConnectionInterface $connection)
        {
            $this->connection = $connection;
        }

        protected function loadInfo()
        {
            if ($this->info !== null) {
                return;
            }

            $info = [
                'collation' => [],
                'charset' => [],
            ];
            $list = $this->connection->query('SHOW COLLATION')->fetchAll();
            foreach ($list as $row) {
                $info['collation'][$row['Collation']] = true;
                $info['charset'][$row['Charset']] = true;
            }

            $this->info = $info;
        }

        public function replaceCharsetOrCollation(array $matches)
        {
            $name = $matches[0];
            $this->loadInfo();
            if (strpos($name, '_') !== false) {
                // Collation
                if (!empty($this->info['collation'][$name])) {
                    return $name;
                }
                // utf8mb4_unicode_520_ci => utf8mb4_unicode_520_ci
                $try = str_replace('_520_', '_', $name, $count);
                if ($count && !empty($this->info['collation'][$try])) {
                    return $try;
                }
                // utf8mb4_unicode_520_ci => utf8_unicode_520_ci
                $try = str_replace('utf8mb4', 'utf8', $name, $count);
                if ($count && !empty($this->info['collation'][$try])) {
                    return $try;
                }
                // utf8mb4_unicode_520_ci => utf8_unicode_ci
                $try = str_replace(['utf8mb4', '_520_'], ['utf8', '_'], $name, $count);
                if ($count && !empty($this->info['collation'][$try])) {
                    return $try;
                }
            } else {
                // Encoding
                if (!empty($this->info['charset'][$name])) {
                    return $name;
                }
                $try = str_replace('utf8mb4', 'utf8', $name, $count);
                if ($count && !empty($this->info['charset'][$try])) {
                    return $try;
                }
            }
            return $name;
        }
    }
endif;

if (!class_exists('UmbrellaImportState', false)):
    class UmbrellaImportState
    {
        public $file;
        /** @var string Collects skipped statements up to a certain buffer length. */
        public $skip = '';
        /** @var int Counts skipped statements. */
        public $skipCount = 0;
        /** @var int Keeps skipped statements' total size. */
        public $skipSize = 0;
        /** @var UmbrellaImportDump[] File dumps that should be imported. */
        public $files = [];

        /** @var int Maximum buffer size for skipped statements. */
        private $skipBuffer = 0;

        /**
         * @param array $data       State array; empty state means there's nothing to process. Every file that should be imported
         *                          must contain the props $state['files'][$i]['path'] and $state['files'][$i]['size'].
         * @param int   $skipBuffer Maximum buffer size for skipped statement logging.
         *
         * @return UmbrellaImportState
         */
        public static function fromArray(array $data, $skipBuffer = 0)
        {
            $state = new self;
            $state->skipBuffer = $skipBuffer;

            foreach ((array)@$data['files'] as $i => $dump) {
                $state->files[$i] = new UmbrellaImportDump(
                    $dump['size'],
                    $dump['processed'],
                    $dump['path'],
                    $dump['encoding'],
                    $dump['source'],
                    $dump['type']
                );
            }

            $state->skip = 0; //(string)@$data['skip'];
            $state->skipCount = 0; // (int)@$data['skipCount'];
            $state->skipSize = 0; // (int)@$data['skipSize'];
            return $state;
        }

        /**
          * @return ClonerImportDump|null The next dump in the queue, or null if there are none left.
          */
        public function next()
        {
            foreach ($this->files as $file) {
                if ($file->processed < $file->size) {
                    return $file;
                }
            }
            return null;
        }

        /**
         * Pushes the first available file dump to the end of the queue.
         */
        public function pushNextToEnd()
        {
            $carry = null;
            foreach ($this->files as $i => $file) {
                if ($file->size === $file->processed) {
                    continue;
                }
                $carry = $file;
                unset($this->files[$i]);
                $this->files = array_values($this->files);
                break;
            }

            if ($carry === null) {
                return;
            }

            $this->files[] = $carry;
        }

        /**
         * Add a "skipped statement" to the state if there's any place left in state's "skipped statement" buffer.
         * Also updates state's "skipped statement" count and size.
         *
         * @param string $statements Statements that were skipped.
         */
        public function skipStatement($statements)
        {
            $length = strlen($statements);
            if (strlen($this->skip) + $length <= $this->skipBuffer / 2) {
                // Only write full statements to the buffer if it won't exceed half the buffer.
                $this->skip .= $statements;
            } elseif ($length + 200 <= $this->skipBuffer) {
                // We have enough space in the buffer to log the excerpt, but don't overflow the buffer, skip logging
                // when we reach its limit.
                $this->skip .= sprintf('/* query too big (%d bytes), excerpt: %s */;', $length, substr($statements, 0, 100));
            }

            $this->skipCount++;
            $this->skipSize += $length;
        }
    }
endif;

if(!class_exists('UmbrellaDumpScanner')):
    class UmbrellaDumpScanner
    {
        const INSERT_REPLACEMENT_PATTERN = '#^INSERT\\s+INTO\\s+(`?)[^\\s`]+\\1\\s+(?:\([^)]+\)\\s+)?VALUES\\s*#';
        // File handle.
        private $handle;
        // 0 - unknown ending
        // 1 - \n ending
        // 2 - \r\n ending
        private $rn = 0;
        private $cursor = 0;
        // Buffer that holds up to one statement.
        private $buffer = '';

        /**
         * @param string $path
         *
         * @throws UmbrelaException
         */
        public function __construct($path)
        {
            $this->handle = @fopen($path, 'rb');
            if (!is_resource($this->handle)) {
                throw new UmbrelaException('Could not open database dump file', 'db_dump_open');
            }
        }

        /**
         * @param int $maxCount
         * @param int $maxSize
         *
         * @return string Up to $maxCount statements or until half of $maxSize (in bytes) is reached.
         *
         * @throws UmbrelaException
         */
        public function scan($maxCount, $maxSize)
        {
            $lineBuffer = '';
            $buffer = '';
            $delimited = false;
            $count = 0;
            $inserts = false;
            while (true) {
                if (strlen($this->buffer)) {
                    $line = $this->buffer;
                    $this->buffer = '';
                } else {
                    $line = fgets($this->handle);
                    if ($line === false) {
                        if (feof($this->handle)) {
                            // So, this is needed...
                            break;
                        }
                        throw new UmbrelaException('Could not read database dump line', 'db_dump_read_line');
                    }
                    $this->cursor += strlen($line);
                }
                $len = strlen($line);
                if ($this->rn === 0) {
                    // Run only once - detect line ending.
                    if (substr_compare($line, "\r\n", $len - 2) === 0) {
                        $this->rn = 2;
                    } else {
                        $this->rn = 1;
                    }
                }

                if (strlen($lineBuffer) === 0) {
                    // Detect comments.
                    if ($len <= 2 + $this->rn) {
                        if ($this->rn === 2) {
                            if ($line === "--\r\n" || $line === "\r\n") {
                                continue;
                            }
                        } else {
                            if ($line === "--\n" || $line === "\n") {
                                continue;
                            }
                        }
                    }
                    if (strncasecmp($line, '-- ', 3) === 0) {
                        continue;
                    }
                    if (preg_match('{^\s*$}', $line)) {
                        continue;
                    }
                }

                if (($len >= 2 && $this->rn === 1 && substr_compare($line, ";\n", $len - 2) === 0)
                    || ($len >= 3 && $this->rn === 2 && substr_compare($line, ";\r\n", $len - 3) === 0)
                ) {
                    // Statement did end - fallthrough. This logic just makes more sense to write.
                } else {
                    $lineBuffer .= $line;
                    continue;
                }
                if (strlen($lineBuffer)) {
                    $line = $lineBuffer . $line;
                    $lineBuffer = '';
                }
                // Hack, but it's all for the greater good. The mysqldump command dumps statements
                // like "/*!50013 DEFINER=`user`@`localhost` SQL SECURITY DEFINER */" which require
                // super-privileges. That's way too troublesome, so just skip those statements.
                if (strncmp($line, '/*!50013 DEFINER=`', 18) === 0) {
                    continue;
                }
                // /*!50003 CREATE*/ /*!50017 DEFINER=`foo`@`localhost`*/ /*!50003 TRIGGER `wp_hplugin_root` BEFORE UPDATE ON `wp_hplugin_root` FOR EACH ROW SET NEW.last_modified = NOW() */;
                if (strncmp($line, '/*!50003 CREATE*/ /*!50017 DEFINER=', 35) === 0) {
                    $line = preg_replace('{/\*!50017 DEFINER=.*?(\*/)}', '', $line, 1);
                }
                if (strncmp($line, '/*!50001 CREATE ALGORITHM=', 26) === 0) {
                    continue;
                }
                if (strncmp($line, '/*!50001 VIEW', 13) === 0) {
                    continue;
                }
                $count++;
                if ($delimited) {
                    // We're inside a block that looks like this:
                    //
                    //  DELIMITER ;;
                    //  /*!50003 CREATE*/ /*!50017 DEFINER=`user`@`localhost`*/ /*!50003 TRIGGER `wp_hlogin_default_storage_table` BEFORE UPDATE ON `wp_hlogin_default_storage_table`
                    //  FOR EACH ROW SET NEW.last_modified = NOW() */;;
                    //  DELIMITER ;
                    //
                    // Since the DELIMITER statement does nothing when not in the CLI context, we need to merge the delimited statements
                    // manually into a single statement.
                    if (strncmp($line, 'DELIMITER ;', 11) === 0) {
                        break;
                    }
                    // Replace the new delimiter with the default one (remove one semicolon).
                    if (($this->rn === 1 && substr_compare($line, ";;\n", -3, 3) === 0)
                        || ($this->rn === 2 && substr_compare($line, ";;\r\n", -4, 4) === 0)
                    ) {
                        $line = substr($line, 0, -($this->rn + 1)); // strip ";\n" or ";\r\n" at the end.
                    }
                    $buffer .= $line . "\n";
                    continue;
                } elseif (strncmp($line, 'DELIMITER ;;', 12) === 0) {
                    $delimited = true;
                    continue;
                }
                if (strncmp($line, 'INSERT INTO ', 12) === 0) {
                    $inserts = true;
                    if (strlen($buffer) === 0) {
                        $buffer = 'INSERT IGNORE INTO ' . substr($line, strlen('INSERT INTO '), -(1 + $this->rn)); // Strip the ";\n" or ";\r\n" at the end
                    } else {
                        if (strlen($buffer) + strlen($line) >= max(1, $maxSize / 2)) {
                            $this->buffer = $line;
                            break;
                        }
                        $newLine = preg_replace(self::INSERT_REPLACEMENT_PATTERN, ', ', $line, 1, $c);
                        $newLine = substr($newLine, 0, -(1 + $this->rn));
                        if ($c !== 1) {
                            throw new UmbrelaException(sprintf('Could not parse INSERT line: %s', $line), 'parse_insert_line');
                        }
                        $buffer .= $newLine;
                    }
                    if ($count >= $maxCount) {
                        break;
                    }
                    continue;
                } elseif ($inserts) {
                    // $buffer is not empty and we aren't inserting anything - break.
                    $this->buffer = $line;
                } else {
                    $buffer = $line;
                }
                break;
            }
            if ($inserts) {
                $buffer .= ';';
            }
            return $buffer;
        }

        /**
         * @param int $offset
         *
         * @throws UmbrellaException
         */
        public function seek($offset)
        {
            $seek = @fseek($this->handle, $offset);
            if ($seek === false) {
                throw new UmbrellaException('Could not seek database dump file', 'seek_file');
            }
            $this->cursor = $offset;
        }

        public function tell()
        {
            return $this->cursor - strlen($this->buffer);
        }

        public function close()
        {
            fclose($this->handle);
        }
    }
endif;

if (!class_exists('ReadableRecursiveFilterIterator')) {
    class ReadableRecursiveFilterIterator extends RecursiveFilterIterator
    {
        #[\ReturnTypeWillChange]
        public function accept()
        {
            try {
                return $this->current()->isReadable();
            } catch(Exception $e) {
                return false;
            }
        }
    }
}

if (!class_exists('UmbrellaErrorHandler', false)):
    class UmbrellaErrorHandler
    {
        const MAX_SIZE_LOG_FILE = 5242880; // 5 Mo = 5 * 1024 * 1024 octets

        private $logFile;
        private $reservedMemory;
        private static $lastError;
        private $requestID;

        public function __construct($logFile)
        {
            $this->logFile = $logFile;
        }

        public function register()
        {
            $this->reservedMemory = str_repeat('x', 10240);
            register_shutdown_function([$this, 'handleFatalError']);
            set_error_handler([$this, 'handleError']);
            set_exception_handler([$this, 'handleException']);
        }

        public function unregister()
        {
            if(file_exists($this->logFile)) {
                @unlink($this->logFile);
            }
        }

        /**
         * @return array
         */
        public static function lastError()
        {
            return self::$lastError;
        }

        public function handleError($type, $message, $file, $line)
        {
            self::$lastError = compact('message', 'type', 'file', 'line');
            if (error_reporting() === 0) {
                // Muted error.
                return;
            }
            if (!strlen($message)) {
                $message = 'empty error message';
            }
            $args = func_get_args();
            if (count($args) >= 6 && $args[5] !== null && $type & E_ERROR) {
                // 6th argument is backtrace.
                // E_ERROR fatal errors are triggered on HHVM when
                // hhvm.error_handling.call_user_handler_on_fatals=1
                // which is the way to get their backtrace.
                $this->handleFatalError(compact('type', 'message', 'file', 'line'));

                return;
            }
            list($file, $line) = self::getFileLine($file, $line);
            $this->log(sprintf('%s: %s in %s on line %d', self::codeToString($type), $message, $file, $line));
        }

        private static function getFileLine($file, $line)
        {
            if (__FILE__ !== $file) {
                return [$file, $line];
            }
            if (function_exists('__bundler_sourcemap')) {
                $globalOffset = 0;
                foreach (__bundler_sourcemap() as $offsetPath) {
                    list($offset, $path) = $offsetPath;
                    if ($line <= $offset) {
                        return [$path, $line - $globalOffset + 1];
                    }
                    $globalOffset = $offset;
                }
            }
            return [$file, $line];
        }

        /**
         * @param Exception|Error $e
         */
        public function handleException($e)
        {
            list($file, $line) = self::getFileLine($e->getFile(), $e->getLine());
            $this->log(sprintf('Unhandled exception in file %s line %d: %s', $file, $line, $e->getMessage()));
            exit;
        }

        public function handleFatalError(array $error = null)
        {
            $this->reservedMemory = null;
            if ($error === null) {
                // Since default PHP implementation doesn't call error handlers on fatal errors, the self::$lastError
                // variable won't be updated. That's why this is the only place where we call error_get_last() directly.
                $error = error_get_last();
            }
            if (!$error) {
                return;
            }
            if (!in_array($error['type'], [E_PARSE, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
                return;
            }
            list($file, $line) = self::getFileLine($error['file'], $error['line']);
            $message = sprintf('%s: %s in %s on line %d', self::codeToString($error['type']), $error['message'], $file, $line);
            $this->log($message);
            exit;
        }

        private function log($message)
        {
            if (file_exists($this->logFile) && filesize($this->logFile) >= self::MAX_SIZE_LOG_FILE) {
                return;
            }

            if (($fp = fopen($this->logFile, 'a')) === false) {
                return;
            }
            if (flock($fp, LOCK_EX) === false) {
                fclose($fp);
                return;
            }
            if (fwrite($fp, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message)) === false) {
                fclose($fp);
                return;
            }
            fclose($fp);
        }

        private static function codeToString($code)
        {
            switch ($code) {
                case E_ERROR:
                    return 'E_ERROR';
                case E_WARNING:
                    return 'E_WARNING';
                case E_PARSE:
                    return 'E_PARSE';
                case E_NOTICE:
                    return 'E_NOTICE';
                case E_CORE_ERROR:
                    return 'E_CORE_ERROR';
                case E_CORE_WARNING:
                    return 'E_CORE_WARNING';
                case E_COMPILE_ERROR:
                    return 'E_COMPILE_ERROR';
                case E_COMPILE_WARNING:
                    return 'E_COMPILE_WARNING';
                case E_USER_ERROR:
                    return 'E_USER_ERROR';
                case E_USER_WARNING:
                    return 'E_USER_WARNING';
                case E_USER_NOTICE:
                    return 'E_USER_NOTICE';
                case E_STRICT:
                    return 'E_STRICT';
                case E_RECOVERABLE_ERROR:
                    return 'E_RECOVERABLE_ERROR';
                case E_DEPRECATED:
                    return 'E_DEPRECATED';
                case E_USER_DEPRECATED:
                    return 'E_USER_DEPRECATED';
            }
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50300) {
                switch ($code) {
                    case E_DEPRECATED:
                        return 'E_DEPRECATED';
                    case E_USER_DEPRECATED:
                        return 'E_USER_DEPRECATED';
                }
            }
            return 'E_UNKNOWN';
        }
    }
endif;

if (!class_exists('UmbrellaWebSocketRestoration')):
    class UmbrellaWebSocketRestoration extends UmbrellaWebSocket
    {
        protected function buildHeaders()
        {
            $headers = [
                'GET / HTTP/1.1',
                'Host: ' . $this->host,
                'Upgrade: websocket',
                'Connection: Upgrade',
                'Origin: ' . $this->origin,
                'X-Request-Id: ' . $this->context->getRequestId(),
                'X-File-Cursor: ' . $this->context->getFileCursor(),
                'X-Database-Cursor: ' . $this->context->getDatabaseCursor(),
                'X-Retry-From-Websocket-Server: ' . $this->context->getRetryFromWebsocketServer(),
                'X-Zip-HasBeen-Received: ' . $this->context->getZipHasBeenReceived(),
                'X-Filename-Sent: ' . $this->context->getFilenameSent(),
                'X-FileSize: ' . $this->context->getFileSize(),
                'X-Chunk: ' . $this->context->getChunk(),
                'X-Chunk-Factor: ' . $this->context->getChunkFactor(),
                'Sec-WebSocket-Key: ' . $this->key,
                'Sec-WebSocket-Version: ' . $this->wsVersion,
            ];

            return implode("\r\n", $headers) . "\r\n\r\n";
        }
    }
endif;

if (!class_exists('UmbrellaRestoreCleanup', false)):
    class UmbrellaRestoreCleanup
    {
        protected $context;

        public function __construct($params)
        {
            $this->context = $params['context'];
        }

        public function handleDatabase()
        {
            $this->removeDirectory($this->context->getRootDatabaseBackupDirectory());
        }

        public function handleEndProcess()
        {
            $filePath = $this->context->getBaseDirectory() . DIRECTORY_SEPARATOR . 'restore.php';

            if(file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        protected function removeDirectory($path)
        {
            if (!file_exists($path)) {
                return;
            }

            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    $this->removeDirectory($filePath);
                } else {
                    @unlink($filePath);
                }
            }

            @rmdir($path);
        }
    }
endif;

if (!class_exists('ZipHandler')) {
    class ZipHandler
    {
        protected $zipFilePath;

		protected $extractToPath;

		protected $context;

        public function __construct($zipFilePath, $extractToPath, $options)
        {
            $this->zipFilePath = $zipFilePath;
            $this->extractToPath = $extractToPath;
            $this->context = $options['context'];
        }

        public function extract()
        {
            if (class_exists('ZipArchive')) {
                return $this->extractWithZipArchive();
            }

            return $this->extractWithPclZip();
        }

        protected function extractWithZipArchive()
        {
            try {
                $zip = new ZipArchive;
                if ($zip->open($this->zipFilePath) === true) {
                    $zip->extractTo($this->extractToPath);
                    $zip->close();
                }
            } catch (Exception $e) {
                $this->extractWithPclZip();
            }
        }

        protected function extractWithPclZip()
        {
            $pclPath = $this->context->getBaseDirectory() . DIRECTORY_SEPARATOR . 'wp-admin/includes/class-pclzip.php';
            if (!file_exists($pclPath)) {
                return;
            }

            require_once $pclPath;

            $archive = new PclZip($this->zipFilePath);

            if ($archive->extract(PCLZIP_OPT_PATH, $this->extractToPath) == 0) {
                return [
                    'success' => false,
                    'error' => $archive->errorInfo(true)
                ];
            }

            return [
                'success' => true
            ];
        }
    }
}

if (!class_exists('UmbrellaReceiveZipRestoration')):
    class UmbrellaReceiveZipRestoration extends UmbrellaAbstractProcessRestore
    {
        public function seemsLikeAFilePath($path)
        {
            $regex = "/^(\/|\.\/|\.\.\/)?([\w,\s-]+\/)*([\w,\s-]+\/)?([\w,\s-]+)?(\.[\w]+)?$/";

            return preg_match($regex, $path);
        }

        public function receive()
        {
            $isRunning = true;
            $code = null;
            $retry = 0;
            $handle = null;
            $finish = false;
            $filePath = null;

            global $safeTimeLimit, $startTimer;

            while ($isRunning) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    throw new UmbrellaPreventMaxExecutionTime(); // send the cursor to the server
                    break; // Stop if we are close to the time limit
                }

                $dataFrame = json_encode([
                    'code' => $code,
                    'retry' => $retry,
                ]);
                $this->socket->writeFrame('NEXT_ZIP:' . $dataFrame);
                $data = $this->socket->readFrameJson();

                if ($data === false) {
                    // Send a error
                    break;
                }

                if (!isset($data['type'])) {
                    // With this code, we can retry the chunk that failed and change the "CHUNK_FACTOR"
                    $code = 'chunk_failed';
                    $retry++;

                    if($retry > 4) {
                        $code = 'chunk_failed_max_retry';
                    }

                    continue;
                } else {
                    $code = null;
                    $retry = 0;
                }

                $fileName = $data['fileName'];
                $filePath = $this->context->getRootRestoreDirectory() . $fileName;

                switch ($data['type']) {
                    case 'metadata':

                        // Check if this string is a file name
                        if(!$this->seemsLikeAFilePath($fileName)) {
                            $isRunning = false;
                            break;
                        }

                        $dirPath = dirname($filePath);
                        if (!is_dir($dirPath)) {
                            mkdir($dirPath, 0777, true);
                        }

                        $handle = fopen($filePath, 'wb');
                        break;
                    case 'chunk':
                        $fileChunk = base64_decode($data['fileChunk']);

                        // Check if this string is a file name
                        if(!$this->seemsLikeAFilePath($fileName)) {
                            $isRunning = false;
                            break;
                        }

                        if($handle === null) {
                            $handle = fopen($filePath, 'a'); // Open the file in append mode
                            break;
                        }

                        if($handle === false) {
                            $isRunning = false;
                            break;
                        }

                        fwrite($handle, $fileChunk);
                        fflush($handle);

                        break;
                    case 'end_file':
                        if(is_resource($handle)) {
                            fclose($handle);
                        }
                        $handle = null;

                        // Only increment the cursor if the chunk was successful or if the chunk failed and we are not retrying
                        if($code === 'chunk_failed') {
                            break;
                        }

                        break;
                    case 'end_transfer':
                        if($handle !== null && is_resource($handle)) {
                            fclose($handle);
                        }

                        $isRunning = false;
                        $finish = true;
                        break;
                }
            }

            return [
                'finish' => $finish,
                'filePath' => $filePath
            ];
        }
    }
endif;

if (!class_exists('UmbrellaDatabaseRestoration')):
    class UmbrellaDatabaseRestoration extends UmbrellaAbstractProcessRestore
    {
        public function killDatabaseProcessList()
        {
            // Use a random identifier so we don't pick up the current process.
            $rand = md5(uniqid('', true));

            $list = $this->connection->query("SELECT ID, INFO FROM information_schema.PROCESSLIST WHERE `USER` = :user AND `DB` = :db AND `INFO` NOT LIKE '%{$rand}%'", [
                'user' => $this->connection->getConfiguration()->user,
                'db' => $this->connection->getConfiguration()->name,
            ])->fetchAll();
            foreach ($list as $process) {
                $this->connection->execute("KILL {$process['ID']}");
            }
            $this->connection->execute('UNLOCK TABLES');
        }

        public function receive()
        {
            $isRunning = true;
            $code = null;
            $retry = 0;
            $handle = null;
            $canImport = false;

            while ($isRunning) {
                $cursor = $this->context->getDatabaseCursor();

                $dataCursor = json_encode(['cursor' => $cursor]);

                $this->socket->writeFrame('NEXT_TABLE:' . $dataCursor);

                $data = $this->socket->readFrameJson();

                if ($data === false) {
                    echo "Error while reading frame.\n";
                    break;
                }

                if (!isset($data['type'])) {
                    // With this code, we can retry the chunk that failed and change the "CHUNK_FACTOR"
                    $code = 'chunk_failed';
                    $retry++;

                    if($retry > 4) {
                        $code = 'chunk_failed_max_retry';
                    }

                    continue;
                } else {
                    $code = null;
                    $retry = 0;
                }

                switch ($data['type']) {
                    case 'metadata':
                        $fileName = $data['fileName'];

                        $filePath = $this->context->getBaseDirectory() . $fileName;

                        $dirPath = dirname($filePath);
                        if (!is_dir($dirPath)) {
                            mkdir($dirPath, 0777, true);
                        }

                        $handle = fopen($filePath, 'wb');
                        break;
                    case 'chunk':
                        $fileChunk = base64_decode($data['fileChunk']);

                        fwrite($handle, $fileChunk);
                        fflush($handle);

                        break;
                    case 'end_file':
                        if(is_resource($handle)) {
                            fclose($handle);
                        }
                        $handle = null;

                        // Only increment the cursor if the chunk was successful or if the chunk failed and we are not retrying
                        if($code === 'chunk_failed') {
                            break;
                        }

                        $this->context->incrementDatabaseCursor();
                        break;
                    case 'end_transfer':
                        if($handle !== null && is_resource($handle)) {
                            fclose($handle);
                        }
                        $isRunning = false;
                        $canImport = true;
                        break;
                }
            }

            return $canImport;
        }

        public function importTables()
        {
            try {
                $this->killDatabaseProcessList();

                $path = $this->context->getRootDatabaseBackupDirectory();

                $files = glob($path . '/*.sql');

                $stateFiles = [];
                foreach ($files as $filePath) {
                    // Get size of file to import

                    $stateFiles[] = [
                        'size' => getFsStat($filePath)->getSize(),
                        'path' => $filePath,
                        'encoding' => 'utf8',
                        'processed' => 0,
                        'source' => basename($filePath),
                        'type' => UmbrellaTableType::REGULAR
                    ];
                }

                $state = UmbrellaImportState::fromArray([
                    'files' => $stateFiles
                ], 10 << 10);

                $filters = [];
                $import = new DatabaseImportTable();

                $import->import($this->connection, $state);
            } catch (\Exception $e) {
                return false;
            }

            return true;
        }
    }
endif;

if (!class_exists('UmbrellaFileRestoration')):
    class UmbrellaFileRestoration extends UmbrellaAbstractProcessRestore
    {
        public function seemsLikeAFilePath($path)
        {
            $regex = "/^(\/|\.\/|\.\.\/)?([\w,\s-]+\/)*([\w,\s-]+\/)?([\w,\s-]+)?(\.[\w]+)?$/";

            return preg_match($regex, $path);
        }

        public function receive()
        {
            $isRunning = true;
            $code = null;
            $retry = 0;
            $handle = null;

            while ($isRunning) {
                $cursor = $this->context->getFileCursor();

                $dataFrame = json_encode([
                    'cursor' => $cursor,
                    'code' => $code,
                    'retry' => $retry
                ]);
                $this->socket->writeFrame('NEXT_FILE:' . $dataFrame);
                $data = $this->socket->readFrameJson();

                if ($data === false) {
                    // Send a error
                    break;
                }

                if (!isset($data['type'])) {
                    // With this code, we can retry the chunk that failed and change the "CHUNK_FACTOR"
                    $code = 'chunk_failed';
                    $retry++;

                    if($retry > 4) {
                        $code = 'chunk_failed_max_retry';
                    }

                    continue;
                } else {
                    $code = null;
                    $retry = 0;
                }

                $fileName = $data['fileName'];
                $filePath = $this->context->getBaseDirectory() . $fileName;

                switch ($data['type']) {
                    case 'metadata':

                        // Check if this string is a file name
                        if(!$this->seemsLikeAFilePath($fileName)) {
                            $this->context->incrementFileCursor();
                            break;
                        }

                        $dirPath = dirname($filePath);
                        if (!is_dir($dirPath)) {
                            mkdir($dirPath, 0777, true);
                        }

                        $handle = fopen($filePath, 'wb');
                        break;
                    case 'chunk':
                        $fileChunk = base64_decode($data['fileChunk']);

                        // Check if this string is a file name
                        if(!$this->seemsLikeAFilePath($fileName)) {
                            $this->context->incrementFileCursor();
                            break;
                        }

                        if($handle === null) {
                            $handle = fopen($filePath, 'a'); // Open the file in append mode
                            break;
                        }

                        if($handle === false) { // DANGEROUS : if the file is not writable, we will lose the data
                            $this->context->incrementFileCursor();
                            break;
                        }

                        fwrite($handle, $fileChunk);
                        fflush($handle);

                        break;
                    case 'end_file':
                        if(is_resource($handle)) {
                            fclose($handle);
                        }
                        $handle = null;

                        // Only increment the cursor if the chunk was successful or if the chunk failed and we are not retrying
                        if($code === 'chunk_failed') {
                            break;
                        }

                        $this->context->incrementFileCursor();
                        break;
                    case 'end_transfer':
                        if($handle !== null && is_resource($handle)) {
                            fclose($handle);
                        }

                        $isRunning = false;
                        break;
                }
            }
        }
    }
endif;

if (!class_exists('Umbrellafsfileinfo', false)):
    class UmbrellaFSFileInfo
    {
        /** @var string */
        private $path;
        /** @var UmbrellaFSFileInfo */
        private $stat;
        /** @var string[]|null */
        private $children;

        /**
         * @param string         $relPath
         * @param UmbrellaFSFileInfo $stat
         * @param string[]|null  $children
         */
        public function __construct($relPath, UmbrellaFSFileInfo $stat, array $children = null)
        {
            $this->path = $relPath;
            $this->stat = $stat;
            $this->children = $children;
        }

        /**
         * @return string
         */
        public function getPath()
        {
            return $this->path;
        }

        /**
         * @return UmbrellaFSFileInfo
         */
        public function getStat()
        {
            return $this->stat;
        }

        /**
         * @param string[]|null $children
         */
        public function setChildren(array $children = null)
        {
            $this->children = $children;
        }

        /**
         * @return string[]|null
         */
        public function getChildren()
        {
            return $this->children;
        }
    }
endif;

if(!class_exists('UmbrellaHTMLSynchronize')):
    class UmbrellaHTMLSynchronize
    {
        public function render()
        {
            ?>
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport"
                    content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
                <meta http-equiv="X-UA-Compatible" content="ie=edge">
                <title>Restoration</title>
                <style>
                    body {
                        color: #333;
                        margin: 0;
                        height: 100vh;
                        background-color: #4f46e5;
                        font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
                    }
                    .content {
                        display:flex;
                        align-items: center;
                        justify-content: center;

                    }

                    .box{
                        margin-top: 32px;
                        background-color: #fff;
                        padding:16px;
                        max-width: 600px;
                        border-radius: 16px;
                    }


                </style>
            </head>
            <body>

            <div class="content">
                <div class="box">
                    <p>
                        A process is running in the background to synchronize your website
                    </p>
                    <p>This file will be deleted when the process is finished</p>
                </div>
            </div>


            </body>
            </html>
<?php
die;
        }
    }
endif;

define('UMBRELLA_RESTORE_KEY', '[[UMBRELLA_RESTORE_KEY]]');
define('UMBRELLA_DB_HOST', '[[UMBRELLA_DB_HOST]]');
define('UMBRELLA_DB_NAME', '[[UMBRELLA_DB_NAME]]');
define('UMBRELLA_DB_USER', '[[UMBRELLA_DB_USER]]');
define('UMBRELLA_DB_PASSWORD', '[[UMBRELLA_DB_PASSWORD]]');
define('UMBRELLA_DB_SSL', '[[UMBRELLA_DB_SSL]]');

set_time_limit(3600);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

date_default_timezone_set('UTC');
ini_set('memory_limit', '512M');

$request = [];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        global $HTTP_RAW_POST_DATA;
        $requestBody = $HTTP_RAW_POST_DATA;

        if ($requestBody === null || strlen($requestBody) === 0) {
            $requestBody = file_get_contents('php://input');
        }
        if (strlen($requestBody) === 0 && defined('STDIN')) {
            $requestBody = stream_get_contents(STDIN);
        }

        $request = json_decode($requestBody, true);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['data'])) {
        $request = json_decode(base64_decode($_GET['data']), true);
    }
} catch (\Exception $e) {
    @unlink(__FILE__);
    die;
}

$html = new UmbrellaHTMLSynchronize();

$action = '';
if (isset($_GET['action']) && is_string($_GET['action']) && strlen($_GET['action'])) {
    $action = $_GET['action'];
}

switch ($action) {
    case '':
    case 'check-communication':
        $html->render();
        return;
}

if (!defined('UMBRELLA_RESTORE_KEY')) {
    $html->render();
    return;
}

if (UMBRELLA_RESTORE_KEY === '[[UMBRELLA_RESTORE_KEY]]') {
    $html->render();
    return;
}

if (!isset($_GET['umbrella-restore-key'])) {
    $html->render();
    return;
}

$key = $_GET['umbrella-restore-key'];

if (!hash_equals(UMBRELLA_RESTORE_KEY, $_GET['umbrella-restore-key'])) {
    $html->render();
    @unlink(__FILE__);
    return;
}

$actionsAvailable = [
    'request',
    'cleanup'
];

if (!in_array($action, $actionsAvailable, true)) {
    $html->render();
    @unlink(__FILE__);
    return;
}

$host = $request['host'];

$validHosts = [
    '127.0.0.1',
    '1.restoration.wp-umbrella.com',
    '2.restoration.wp-umbrella.com',
    '3.restoration.wp-umbrella.com',
    '4.restoration.wp-umbrella.com',
    '5.restoration.wp-umbrella.com',
    '6.restoration.wp-umbrella.com',
    '7.restoration.wp-umbrella.com',
    '8.restoration.wp-umbrella.com',
    '9.restoration.wp-umbrella.com',
];

if (!in_array($host, $validHosts, true)) {
    $html->render();
    return;
}

$errorHandler = new UmbrellaErrorHandler(dirname(__FILE__) . '/restore_error_log');
$errorHandler->register();

global $startTimer;
$startTimer = time();

global $safeTimeLimit;
$maxExecutionTime = ini_get('max_execution_time');
if ($maxExecutionTime === false || $maxExecutionTime === '' || (int) $maxExecutionTime < 1) {
    $maxExecutionTime = 60;
}

$preventTimeout = $request['seconds_prevent_timeout'] ?? 6;

$safeTimeLimit = $maxExecutionTime - $preventTimeout;

/**
 * Init Context
 */
$context = new UmbrellaContext([
    'requestId' => $request['request_id'],
    'baseDirectory' => __DIR__,
    'tables' => $request['tables'] ?? [],
    'fileCursor' => $request['file_cursor'] ?? 0,
    'databaseCursor' => $request['database_cursor'] ?? 0,
    'retryFromWebsocketServer' => $request['retryFromWebsocketServer'] ?? false,
    'zipHasBeenReceived' => $request['zipHasBeenReceived'] ?? false,
    'zipFilename' => $request['zipFilename'] ?? null,
    'currentTransfer' => $request['currentTransfer'] ?? [
        'filenameSent' => false,
        'chunk' => 0,
        'fileSize' => 0,
        'chunkFactor' => null,
    ],
    'databaseConnectionInfo' => [
        'user' => defined('UMBRELLA_DB_USER') && UMBRELLA_DB_USER !== '[[UMBRELLA_DB_USER]]' ? UMBRELLA_DB_USER : $request['database']['db_user'],
        'password' => defined('UMBRELLA_DB_PASSWORD') && UMBRELLA_DB_PASSWORD !== '[[UMBRELLA_DB_PASSWORD]]' ? UMBRELLA_DB_PASSWORD : $request['database']['db_password'],
        'host' => defined('UMBRELLA_DB_HOST') && UMBRELLA_DB_HOST !== '[[UMBRELLA_DB_HOST]]' ? UMBRELLA_DB_HOST : $request['database']['db_host'],
        'name' => defined('UMBRELLA_DB_NAME') && UMBRELLA_DB_NAME !== '[[UMBRELLA_DB_NAME]]' ? UMBRELLA_DB_NAME : $request['database']['db_name'],
        'ssl' => defined('UMBRELLA_DB_SSL') && UMBRELLA_DB_SSL !== '[[UMBRELLA_DB_SSL]]' ? UMBRELLA_DB_SSL : $request['database']['db_ssl'],

    ]
]);

$cleanup = new UmbrellaRestoreCleanup([
    'context' => $context,
]);

$finish = false;

$context->createBackupDirectoryIfNotExists();
$context->createRestoreDirectoryIfNotExists();

try {
    $socket = new UmbrellaWebSocketRestoration([
        'host' => $host,
        'port' => $request['port'],
        'transport' => isset($request['transport']) ? $request['transport'] : 'ssl',
        'context' => $context
    ]);
    $socket->connect();

    if ($request['zipHasBeenReceived'] === false) {
        $receiveZipRestoration = new UmbrellaReceiveZipRestoration([
            'socket' => $socket,
            'context' => $context
        ]);
        $response = $receiveZipRestoration->receive();

        // It's better to prevent max execution time here to avoid the script
        // In a second run, the script will continue and finish the restoration
        if ($response['finish'] === true && $response['filePath'] !== null) {
            $socket->sendPreventMaxExecutionTime();
            return;
        }
    }

    if ($context->getZipFilename() !== null) {
        $zipHandler = new ZipHandler(
            $context->getRootRestoreDirectory() . DIRECTORY_SEPARATOR . $context->getZipFilename(),
            $context->getBaseDirectory(),
            [
                'context' => $context,
            ]
        );

        $zipHandler->extract();
    }

    $finish = $request['request_database_restore'] === false; // If no database restore, finish process

    // If need database restoration
    // =======================
    if ($request['request_database_restore'] === true) {
        $dbUser = defined('UMBRELLA_DB_USER') && UMBRELLA_DB_USER !== '[[UMBRELLA_DB_USER]]' ? UMBRELLA_DB_USER : $request['database']['db_user'];
        $dbPassword = defined('UMBRELLA_DB_PASSWORD') && UMBRELLA_DB_PASSWORD !== '[[UMBRELLA_DB_PASSWORD]]' ? UMBRELLA_DB_PASSWORD : $request['database']['db_password'];
        $dbHost = defined('UMBRELLA_DB_HOST') && UMBRELLA_DB_HOST !== '[[UMBRELLA_DB_HOST]]' ? UMBRELLA_DB_HOST : $request['database']['db_host'];
        $dbName = defined('UMBRELLA_DB_NAME') && UMBRELLA_DB_NAME !== '[[UMBRELLA_DB_NAME]]' ? UMBRELLA_DB_NAME : $request['database']['db_name'];
        $dbSsl = defined('UMBRELLA_DB_SSL') && UMBRELLA_DB_SSL !== '[[UMBRELLA_DB_SSL]]' ? UMBRELLA_DB_SSL : $request['database']['db_ssl'];

        $connection = UmbrellaDatabaseFunction::getConnection(
            UmbrellaDatabaseConfiguration::fromArray([
                'db_user' => $dbUser,
                'db_password' => $dbPassword,
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_ssl' => $dbSsl,
            ])
        );

        $databaseRestoration = new UmbrellaDatabaseRestoration([
            'socket' => $socket,
            'context' => $context,
            'connection' => $connection
        ]);

        $finish = $databaseRestoration->importTables();
    }

    if ($finish) {
        $cleanup->handleEndProcess();
        $socket->sendFinish();
    }
} catch (\UmbrellaSocketException $e) {
    $socket->sendError($e);
    $cleanup->handleDatabase();
    $cleanup->handleEndProcess();
} catch (\UmbrellaException $e) {
    $socket->sendError($e);
    $cleanup->handleDatabase();

    $cleanup->handleEndProcess();
} catch (\UmbrellaPreventMaxExecutionTime $e) {
    $finish = false;
    $socket->sendPreventMaxExecutionTime($e->getCursor());
} catch (\UmbrellaDatabasePreventMaxExecutionTime $e) {
    $finish = false;
    $socket->sendPreventDatabaseMaxExecutionTime($e->getCursor());
} catch (\Exception $e) {
    try {
        $socket->sendError(new UmbrellaException($e->getMessage(), 'unknown_error', true));
    } catch (\Exception $e) {
        // Do nothing
    }
    $cleanup->handleDatabase();
    $cleanup->handleEndProcess();
} finally {
    if (isset($connection) && $connection instanceof UmbrellaConnectionInterface) {
        $connection->close();
    }

    if (isset($socket) && $socket instanceof UmbrellaWebSocket) {
        sleep(2); // Wait for the last message to be sent
        $socket->close();
    }

    $errorHandler->unregister();

    if ($finish && file_exists(__FILE__)) {
        $cleanup->handleDatabase();
        @unlink(__FILE__);
    }
}

die;
