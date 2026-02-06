<?php
try {
    if (function_exists('header')) {
        @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        @header('Pragma: no-cache');
        @header('Expires: 0');
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(__FILE__, true);

        // Invalidate cache for umb_checksum and umb_database directories
        $dirs = [__DIR__ . DIRECTORY_SEPARATOR . 'umb_checksum', __DIR__ . DIRECTORY_SEPARATOR . 'umb_database'];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        @opcache_invalidate($file->getRealPath(), true);
                    }
                }
            }
        }
    }
} catch (\Exception $e) {
}

define('UMBRELLA_BACKUP_KEY', '[[UMBRELLA_BACKUP_KEY]]');
define('UMBRELLA_DB_HOST', '[[UMBRELLA_DB_HOST]]');
define('UMBRELLA_DB_NAME', '[[UMBRELLA_DB_NAME]]');
define('UMBRELLA_DB_USER', '[[UMBRELLA_DB_USER]]');
define('UMBRELLA_DB_PASSWORD', '[[UMBRELLA_DB_PASSWORD]]');
define('UMBRELLA_DB_SSL', '[[UMBRELLA_DB_SSL]]');

if (!defined('UMBRELLA_BACKUP_KEY')) {
    die();
    return;
}

if (hash_equals(UMBRELLA_BACKUP_KEY, '[[UMBRELLA_BACKUP_KEY]]')) {
    die();
    return;
}

if (!isset($_GET['umbrella-backup-key'])) {
    die();
    return;
}

if (!function_exists('removeScript')) {
    function removeScript()
    {
        @unlink(__DIR__ . DIRECTORY_SEPARATOR . 'cloner.php');
    }
}

//[[REPLACE]]//

if (defined('WPE_APIKEY')) {
    $cookieValue = md5('wpe_auth_salty_dog|' . WPE_APIKEY);
    setcookie('wpe-auth', $cookieValue, 0, '/');
}

if (function_exists('set_time_limit')) {
    set_time_limit(3600);
}

if (function_exists('error_reporting')) {
    error_reporting(E_ALL);
}
if (function_exists('ini_set')) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    ini_set('memory_limit', '512M');
}

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('UTC');
}

if (function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}

$request = [];
try {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $HTTP_RAW_POST_DATA;
        $requestBody = $HTTP_RAW_POST_DATA;

        if ($requestBody === null || strlen($requestBody) === 0) {
            $requestBody = file_get_contents('php://input');
        }
        if (strlen($requestBody) === 0 && defined('STDIN')) {
            $requestBody = stream_get_contents(STDIN);
        }

        $request = json_decode($requestBody, true);
    } elseif (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['data'])) {
        $callback = 'base64_decode';
        $request = json_decode($callback($_GET['data']), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON');
        }
    }
} catch (\Exception $e) {
    removeScript();
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
        $html->render('check-communication');
        return;
}

$key = $_GET['umbrella-backup-key'];

if (!hash_equals(UMBRELLA_BACKUP_KEY, $_GET['umbrella-backup-key'])) {
    $html->render('hash-not-equal');
    // removeScript();
    return;
}

if (!isset($request['host']) || !isset($request['port'])) {
    $html->render('host-or-port-not-set');
    // removeScript();
    return;
}

if (!isset($request['request_id']) || !isset($request['database_prefix'])) {
    $html->render('request-id-or-base-directory-or-database-prefix-not-set');
    // removeScript();
    return;
}

$actionsAvailable = [
    'request',
    'scan',
    'get_dictionary',
    'cleanup',
    'backup_directory',
    'directory_checkup',
    'scan_integrity',
    'scan_size',
];

if (!in_array($action, $actionsAvailable, true)) {
    $html->render('action-not-available');
    // removeScript();
    return;
}

if (!isset($request['host'])) {
    $html->render('host-not-set');
    // removeScript();
    return;
}

$host = $request['host'];

if (!function_exists('validHost')) {
    function validHost($host)
    {
        if (strpos($host, 'mirror.wp-umbrella.com') !== false) {
            return true;
        }

        return $host === '127.0.0.1';
    }
}

if (!validHost($host)) {
    $html->render('host-not-valid');
    return;
}

$errorHandler = new UmbrellaErrorHandler(dirname(__FILE__) . '/cloner_error_log');
$errorHandler->register();

global $totalFilesSent;
$totalFilesSent = 0;

global $startTimer;
$startTimer = time();

global $safeTimeLimit;
$maxExecutionTime = ini_get('max_execution_time');
if ($maxExecutionTime === false || $maxExecutionTime === '' || (int) $maxExecutionTime < 1) {
    $maxExecutionTime = 30;
}

$preventTimeout = $request['seconds_prevent_timeout'] ?? 6;

$safeTimeLimit = $maxExecutionTime - $preventTimeout; // seconds for preventing timeout

/**
 * Init Context
 */
$context = new UmbrellaContext([
    'action' => $action,
    'requestId' => $request['request_id'],
    'baseDirectory' => $request['base_directory'] ?? __DIR__,
    'tables' => $request['tables'] ?? [],
    'database_prefix' => $request['database_prefix'],
    'incremental_date' => $request['incremental_date'] ?? null, //like '2024-01-01'
    'fileCursor' => $request['file_cursor'] ?? 1, // 1 because the first line is for security
    'databaseDumpCursor' => $request['database_dump_cursor'] ?? 0, // 0 because we don't have dump yet
    'databaseCursor' => $request['database_cursor'] ?? 0,
    'scanCursor' => $request['scan_cursor'] ?? 0, // Used for files
    'scanDirectoryCursor' => $request['scan_directory_cursor'] ?? 0, // Used for directories
    'checkupDirectoriesCursor' => $request['checkup_directories_cursor'] ?? 0, // Used for checking directories
    'checkupDirectories' => $request['checkupDirectories'] ?? [],
    'retryFromWebsocketServer' => $request['retryFromWebsocketServer'] ?? false,
    'options' => [
        'file_size_limit' => $request['file_size_limit'] ?? null,
        'excluded_files' => $request['excluded_files'] ?? [],
        'excluded_directories' => $request['excluded_directories'] ?? [],
        'excluded_extension' => $request['excluded_extension'] ?? [],
        'max_mo_per_file' => $request['max_mo_per_file'] ?? 50,
        'is_sql_partitioned' => $request['is_sql_partitioned'] ?? false,
    ],
    'fileSizeLimits' => $request['file_size_limits'] ?? ['*' => UmbrellaContext::DEFAULT_FILE_SIZE_LIMIT],
    'ack' => $request['ack'] ?? '',
    'last_processed_filename' => $request['last_processed_filename'] ?? '',
    'interval_between_batch' => $request['interval_between_batch'] ?? 0,
    'maximum_lines_by_table_by_batch' => $request['maximum_lines_by_table_by_batch'] ?? [],
]);

$cleanup = new UmbrellaCleanup([
    'context' => $context,
]);

if (!function_exists('clearStatCacheUmbrella')) {
    function clearStatCacheUmbrella($path = null)
    {
        if (PHP_VERSION_ID < 50300 || $path === null) {
            clearstatcache();
            return;
        }
        clearstatcache(true, $path);
    }
}

if (!function_exists('clearMemoryAndCache')) {
    function clearMemoryAndCache($socket)
    {
        if (function_exists('gc_collect_cycles')) {
            $socket->sendLog('Cleared memory');
            gc_collect_cycles();
        } else {
            $socket->sendLog('No gc_collect_cycles function');
        }
    }
}

// Create backup directory if not exists for database backup
$context->createBackupDirectoryIfNotExists();
$context->createChecksumDirectoryIfNotExists();

$finish = false;
$backupFilefinish = false;

$transport = isset($request['transport']) ? $request['transport'] : 'ssl';
if (!in_array($transport, ['ssl', 'tcp'], true)) {
    $html->render('transport-not-valid');
    return;
}

$connection = null;

try {
    $socket = new UmbrellaWebSocket([
        'host' => $host,
        'port' => $request['port'],
        'transport' => $transport,
        'context' => $context
    ]);

    $socket->connect();
    $socket->sendTelemetryCounter('backup.websocket.connected', [
        'request_id' => $context->getRequestId(),
        'transport' => $transport,
        'action' => $action,
        'internal_request' => false,
        'php_version' => PHP_VERSION_ID,
        'file_cursor' => $context->getFileCursor(),
        'database_dump_cursor' => $context->getDatabaseDumpCursor(),
        'database_cursor' => $context->getDatabaseCursor(),
        'scan_cursor' => $context->getScanCursor(),
        'scan_directory_cursor' => $context->getScanDirectoryCursor(),
        'checkup_directories_cursor' => $context->getCheckupDirectoriesCursor(),
        'last_processed_filename' => $context->getLastProcessedFilename(),
        'safe_time_limit' => $safeTimeLimit,
        'ack' => $context->getAck(),
        'origin' => 'plugin'
    ]);

    clearMemoryAndCache($socket);
    clearStatCacheUmbrella(__FILE__);

    $errorHandler->setSocket($socket);

    $dbUser = defined('UMBRELLA_DB_USER') && UMBRELLA_DB_USER !== '[[UMBRELLA_DB_USER]]' ? UMBRELLA_DB_USER : htmlspecialchars($request['database']['db_user'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbPassword = defined('UMBRELLA_DB_PASSWORD') && UMBRELLA_DB_PASSWORD !== '[[UMBRELLA_DB_PASSWORD]]' ? UMBRELLA_DB_PASSWORD : htmlspecialchars($request['database']['db_password'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbHost = defined('UMBRELLA_DB_HOST') && UMBRELLA_DB_HOST !== '[[UMBRELLA_DB_HOST]]' ? UMBRELLA_DB_HOST : htmlspecialchars($request['database']['db_host'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbName = defined('UMBRELLA_DB_NAME') && UMBRELLA_DB_NAME !== '[[UMBRELLA_DB_NAME]]' ? UMBRELLA_DB_NAME : htmlspecialchars($request['database']['db_name'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbSsl = defined('UMBRELLA_DB_SSL') && UMBRELLA_DB_SSL !== '[[UMBRELLA_DB_SSL]]' ? UMBRELLA_DB_SSL : htmlspecialchars($request['database']['db_ssl'], FILTER_SANITIZE_SPECIAL_CHARS);

    $socket->sendLog('[action] ' . $action);

    switch ($action) {
        case 'scan_integrity':
			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_INTEGRITY_SCAN_START);

            $context->addExcludedDirectory(DIRECTORY_SEPARATOR . 'umb_database');

            $scanBackup = new UmbrellaScanBackup([
                'context' => $context,
                'socket' => $socket,
            ]);

            $finishScanIntegrity = $scanBackup->scanAllDirectories([
                'write_checksum_integrity' => true,
                'flag_updated_files' => false,
                'write_size' => false,
            ]);
			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_INTEGRITY_SCAN_END);

            if ($finishScanIntegrity) {
				$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_INTEGRITY_SCAN_SEND_END, [
					'send_to' => 'websocket'
				]);
                $socket->sendFinishScanIntegrity($context->getDirectoryDictionaryPath());
                $finish = true;
            }
            return;
        case 'scan_size':
			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SIZE_SCAN_START);

            $scanBackup = new UmbrellaScanBackup([
                'context' => $context,
                'socket' => $socket,
            ]);

            $finishScanSize = $scanBackup->scanAllDirectories([
                'write_checksum_integrity' => false,
                'flag_updated_files' => false,
                'write_size' => true,
                'full_directory_scan' => $request['full_directory_scan'] ?? false,
            ]);

			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SIZE_SCAN_END);

            if ($finishScanSize) {
				$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SIZE_SCAN_SEND_END, [
					'send_to' => 'websocket'
				]);
                $socket->sendFinishScanSize($context->getDirectoryDictionaryPath());
                $finish = true;
            }
            return;
    }

    /**
     * If the action is backup_directory, we only need to get a directory
     */
    if ($action === 'get_dictionary') {
        $socket->sendLog('[Action: get_dictionary] Start');
        $finishDictionary = false;

        $connection = UmbrellaDatabaseFunction::getConnection(
            UmbrellaDatabaseConfiguration::fromArray([
                'db_user' => $dbUser,
                'db_password' => $dbPassword,
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_ssl' => $dbSsl,
            ])
        );

        $socket->sendTelemetryCounter('backup.scan.started', [
            'request_id' => $context->getRequestId(),
            'type' => 'db',
            'origin' => 'plugin'
        ]);

        $tables = UmbrellaDatabaseFunction::getListTables($connection, $context);

        $scanBackup = new UmbrellaScanBackup([
            'context' => $context,
            'socket' => $socket,
        ]);

        // Scan only if the send file batch not started
        if ($context->hasScanDictionaryFilesBatchNotStarted()) {
			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_DIRECTORY_SCAN_START);
            $socket->sendTelemetryCounter('backup.scan.started', [
                'request_id' => $context->getRequestId(),
                'type' => 'directory',
                'origin' => 'plugin'
            ]);
            $finishDictionary = $scanBackup->scanAllDirectories([
                'write_checksum_integrity' => true,
                'flag_updated_files' => false,
                'write_size' => false,
            ]);
        }

        $connection->close();

        if ($finishDictionary) {
			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_DIRECTORY_SCAN_SEND_END,[
				'send_to' => 'websocket'
			]);
            $socket->sendFinishDictionaryWithIntegrity($context->getDirectoryDictionaryPath());
        }
    } elseif ($action === 'directory_checkup') {
        $checkupDirectories = new UmbrellaCheckupDirectories([
            'context' => $context,
            'socket' => $socket
        ]);

		$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_DIRECTORY_CHECKUP_START);

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $finish = $checkupDirectories->check();
		if ($finish) {
			$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_DIRECTORY_CHECKUP_END, [
				'from' => 'websocket'
			]);
		}
    } else {
        /**
         * BACKUP PROCESS
         */

        $socket->sendLog('File cursor: ' . $context->getFileCursor() . ' Scan cursor: ' . $context->getScanCursor() . ' Database dump cursor: ' . $context->getDatabaseDumpCursor() . ' Scan directory cursor: ' . $context->getScanDirectoryCursor());
        // If need database backup
        // =======================
        if ($request['request_database_backup'] === true && $context->hasFileSendFileNotStarted()) {
            $socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SEND_DATABASE_START, [
				'send_to' => 'websocket'
			]);
            $connection = UmbrellaDatabaseFunction::getConnection(
                UmbrellaDatabaseConfiguration::fromArray([
                    'db_user' => $dbUser,
                    'db_password' => $dbPassword,
                    'db_host' => $dbHost,
                    'db_name' => $dbName,
                    'db_ssl' => $dbSsl,
                ])
            );

            // Table retrieval based on demand
            $tables = UmbrellaDatabaseFunction::getListTables($connection, $context);

            $backupDatabase = new UmbrellaDatabaseBackup([
                'context' => $context,
                'connection' => $connection,
                'socket' => $socket,
            ]);

            $backupDatabase->backup($tables);

            $socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SEND_DATABASE_END, [
				'from' => 'websocket'
			]);

            $connection->close();
            $cleanup->handleDatabase();
        }
        // =======================

        $finish = $request['request_file_backup'] === false; // If no file backup then finish

        // If need file backup
        // =======================
        if ($request['request_file_backup'] === true) {
            $socket->sendLog('Base directory path: ' . $context->getBaseDirectory(), true);
            $socket->sendLog('Directory dictionary path: ' . $context->getDirectoryDictionaryPath(), true);
            $socket->sendLog('Directory dictionary path: ' . $context->getDirectoryDictionaryPath(), true);
            if (!file_exists($context->getDirectoryDictionaryPath())) {
                $socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SEND_FILES_NO_DICTIONARY);
                $scanBackup = new UmbrellaScanBackup([
                    'context' => $context,
                    'socket' => $socket,
                ]);

                $scanBackup->scanAllDirectories([
                    'write_checksum_integrity' => true,
                    'flag_updated_files' => false,
                    'write_size' => false,
                ]);
            }

            $backupFile = new UmbrellaFileBackup([
                'context' => $context,
                'socket' => $socket
            ]);

            $socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SEND_FILES_START, [
				'send_to' => 'websocket'
			]);

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $backupFilefinish = $backupFile->backup();
            $socket->sendLog('Finish file backup: ' . $finish ? 'true' : 'false');
            $socket->sendLog("Total files sent by PHP: $totalFilesSent", true);
        }

        // =======================
    }

    if ($backupFilefinish) {
        $socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_SEND_FILES_END, [
			'from' => 'websocket'
		]);
        $socket->sendBackupFilesFinished();
    }

    if ($finish) {
        $socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_FINISH_SEND,[
			'send_to' => 'websocket'
		]);
        $socket->sendFinish();
        $cleanup->handleEndProcess();
    }
} catch (\UmbrellaSocketException $e) {
    // $cleanup->handleDatabase();
    // $cleanup->handleEndProcess();
} catch (\UmbrellaPreventMaxExecutionTime $e) {
    $socket->sendLog('[error] UmbrellaPreventMaxExecutionTime: ' . $e->getMessage(), true);
    $finish = false;
    $socket->sendPreventMaxExecutionTime($e->getCursor());
	$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_MAX_EXECUTION_TIME, [
		'cursor' => $e->getCursor(),
		'send_to' => 'websocket'
	]);
} catch (\UmbrellaDatabasePreventMaxExecutionTime $e) {
    $socket->sendLog('[error] UmbrellaDatabasePreventMaxExecutionTime: ' . $e->getMessage(), true);
    $finish = false;
    $socket->sendPreventDatabaseMaxExecutionTime($e->getCursor());
	$socket->sendStructuredLog(UmbrellaBackupLogCode::BACKUP_DATABASE_MAX_EXECUTION_TIME, [
		'cursor' => $e->getCursor(),
		'send_to' => 'websocket'
	]);
} catch (\UmbrellaInternalRequestException $e) {
    if (isset($socket)) {
        $socket->sendLog('[error] Internal Exception Error: ' . $e->getMessage(), true);
    }
    // $cleanup->handleEndProcess();
} catch (\UmbrellaException $e) {
    if (isset($socket)) {
        $socket->sendLog('[error] : ' . $e->getMessage(), true);
        $socket->sendError($e);
    }

    $this->sendTelemetryCounter('backup.error', [
        'request_id' => $context->getRequestId(),
        'name' => 'error',
        'message' => $e->getMessage(),
        'origin' => 'plugin'
    ]);
    // $cleanup->handleDatabase();
    // $cleanup->handleEndProcess();
} catch (\Exception $e) {
    if (isset($socket)) {
        $socket->sendLog('[error] Unknown Exception Error: ' . $e->getMessage(), true);
        $socket->sendError(new UmbrellaException($e->getMessage(), 'unknown_error', true));
    }

    $this->sendTelemetryCounter('backup.error', [
        'request_id' => $context->getRequestId(),
        'name' => 'unknown_error',
        'message' => $e->getMessage(),
        'origin' => 'plugin'
    ]);
    // $cleanup->handleDatabase();
    // $cleanup->handleEndProcess();
} finally {
    if (isset($socket)) {
        $socket->sendLog('Finally: Close connection');
    }

    if ($connection !== null) {
        $connection->close();
    }

    if (isset($socket) && $socket instanceof UmbrellaWebSocket) {
        $socket->sendTelemetryCounter('backup.websocket.disconnected', [
            'request_id' => $context->getRequestId(),
            'origin' => 'plugin'
        ]);
        sleep(3); // Wait for the last message to be sent
        $socket->close();
    }

    $errorHandler->unregister();

    unset($totalFilesSent, $startTimer, $safeTimeLimit);

    if ($finish) {
        $socket->sendLog('Finally: is finish');
        $cleanup->handleDatabase();
        $cleanup->handleChecksum();
        removeScript();
    } else {
        ?>
		<!doctype html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport"
				content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
			<meta http-equiv="X-UA-Compatible" content="ie=edge">
			<title>Synchronize</title>
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
In Progress
				</p>
			</div>
		</div>


		</body>
		</html>
	<?php
    }
}

die;
