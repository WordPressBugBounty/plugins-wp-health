<?php

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
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

set_time_limit(3600);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

date_default_timezone_set('UTC');
ini_set('memory_limit', '512M');

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
        $html->render();
        return;
}

$key = $_GET['umbrella-backup-key'];

if (!hash_equals(UMBRELLA_BACKUP_KEY, $_GET['umbrella-backup-key'])) {
    $html->render();
    // removeScript();
    return;
}

if (!isset($request['host']) || !isset($request['port'])) {
    $html->render();
    // removeScript();
    return;
}

if (!isset($request['request_id']) || !isset($request['base_directory']) || !isset($request['database_prefix'])) {
    $html->render();
    // removeScript();
    return;
}

$actionsAvailable = [
    'request',
    'scan',
    'get_dictionary',
    'cleanup',
    'backup_directory',
];

if (!in_array($action, $actionsAvailable, true)) {
    $html->render();
    // removeScript();
    return;
}

if (!isset($request['host'])) {
    $html->render();
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
    $html->render();
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

$internalRequest = false;
if (in_array($action, ['backup_directory'])) {
    $internalRequest = true;
}

/**
 * Init Context
 */
$context = new UmbrellaContext([
    'requestId' => $request['request_id'],
    'baseDirectory' => $request['base_directory'] ?? __DIR__,
    'tables' => $request['tables'] ?? [],
    'database_prefix' => $request['database_prefix'],
    'incremental_date' => $request['incremental_date'] ?? null, //like '2024-01-01'
    'fileCursor' => $request['file_cursor'] ?? 1, // 1 because the first line is for security
    'databaseDumpCursor' => $request['database_dump_cursor'] ?? 0, // 0 because we don't have dump yet
    'databaseCursor' => $request['database_cursor'] ?? 0,
    'scanCursor' => $request['scan_cursor'] ?? 0,
    'internalRequest' => $internalRequest,
    'retryFromWebsocketServer' => $request['retryFromWebsocketServer'] ?? false,
    'options' => [
        'file_size_limit' => $request['file_size_limit'] ?? null,
        'excluded_files' => $request['excluded_files'] ?? [],
        'excluded_directories' => $request['excluded_directories'] ?? [],
        'excluded_extension' => $request['excluded_extension'] ?? [],
    ]
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

try {
    switch ($action) {
        case 'scan':
            $backupFile = new UmbrellaFileBackup([
                'context' => $context,
                'socket' => null
            ]);

            $backupFile->scanOnlyDirectories([
                'with_send_to_socket' => false
            ]); // Scan files and directories for create dictionary
            return;
        case 'cleanup':
            $cleanup->handleEndProcess();
            return;
    }
} catch (\Exception $e) {
    echo "Error:\n";
    echo $e->getMessage();
    var_dump($e);
    die;
}

// Create backup directory if not exists for database backup
$context->createBackupDirectoryIfNotExists();

$finish = false;

$transport = isset($request['transport']) ? $request['transport'] : 'ssl';
if (!in_array($transport, ['ssl', 'tcp'], true)) {
    $html->render();
    // removeScript();
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

    clearMemoryAndCache($socket);
    clearStatCacheUmbrella(__FILE__);

    $errorHandler->setSocket($socket);

    $dbUser = defined('UMBRELLA_DB_USER') && UMBRELLA_DB_USER !== '[[UMBRELLA_DB_USER]]' ? UMBRELLA_DB_USER : htmlspecialchars($request['database']['db_user'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbPassword = defined('UMBRELLA_DB_PASSWORD') && UMBRELLA_DB_PASSWORD !== '[[UMBRELLA_DB_PASSWORD]]' ? UMBRELLA_DB_PASSWORD : htmlspecialchars($request['database']['db_password'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbHost = defined('UMBRELLA_DB_HOST') && UMBRELLA_DB_HOST !== '[[UMBRELLA_DB_HOST]]' ? UMBRELLA_DB_HOST : htmlspecialchars($request['database']['db_host'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbName = defined('UMBRELLA_DB_NAME') && UMBRELLA_DB_NAME !== '[[UMBRELLA_DB_NAME]]' ? UMBRELLA_DB_NAME : htmlspecialchars($request['database']['db_name'], FILTER_SANITIZE_SPECIAL_CHARS);
    $dbSsl = defined('UMBRELLA_DB_SSL') && UMBRELLA_DB_SSL !== '[[UMBRELLA_DB_SSL]]' ? UMBRELLA_DB_SSL : htmlspecialchars($request['database']['db_ssl'], FILTER_SANITIZE_SPECIAL_CHARS);

    /**
     * If the action is backup_directory, we only need to get a directory
     */
    if ($action === 'get_dictionary') {
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

        // Table retrieval based on demand
        $tables = UmbrellaDatabaseFunction::getListTables($connection, $context);

        $scanBackup = new UmbrellaScanBackup([
            'context' => $context,
            'socket' => $socket,
        ]);

        $scanBackup->scanTables($tables);

        // Scan only if the send file batch not started
        if ($context->hasScanDictionaryFilesBatchNotStarted()) {
            $socket->sendLog('Start directory scan');
            $scanBackup->scanOnlyDirectories(); // Scan directories for create dictionary
        }

        if ($context->hasFileBatchNotStarted()) {
            $socket->sendLog('Start dictionary files scan');
            $scanBackup->changeModeDirectoryDictionary('r'); // Change mode for read directory dictionary
            $finishDictionary = $scanBackup->scanAndCreateDictionary(); // Scan files for create dictionary
        } else {
            $finishDictionary = true;
        }

        $connection->close();

        /**
         * Internal request = only if we want to try to send the dictionary without any backup
         */
        if ($finishDictionary && $context->getInternalRequest()) {
            // $cleanup->handleEndProcess();
        }

        if ($finishDictionary) {
            $finishSend = $socket->send($context->getDictionaryPath());
            if ($finishSend) {
                $socket->sendFinishDictionary();
            }
        }
    } else {
        /**
         * BACKUP PROCESS
         */

        $socket->sendLog('File cursor: ' . $context->getFileCursor() . ' Scan cursor: ' . $context->getScanCursor() . ' Database dump cursor: ' . $context->getDatabaseDumpCursor());

        // If need database backup
        // =======================
        if ($request['request_database_backup'] === true && $context->hasFileSendFileNotStarted()) {
            $socket->sendLog('Start database backup');
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
            if (!file_exists($context->getDirectoryDictionaryPath())) {
                $socket->sendLog('Directory dictionary not found. Start directory scan.', true);
                $scanBackup = new UmbrellaScanBackup([
                    'context' => $context,
                    'socket' => $socket,
                ]);

                $scanBackup->scanOnlyDirectories();
            }

            $backupFile = new UmbrellaFileBackup([
                'context' => $context,
                'socket' => $socket
            ]);

            $socket->sendLog('Start file backup');

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $finish = $backupFile->backup();
            $socket->sendLog('Finish file backup: ' . $finish ? 'true' : 'false');
            $socket->sendLog("Total files sent by PHP: $totalFilesSent", true);
        }

        // =======================
    }

    if ($finish) {
        $socket->sendLog('Send finish and handle end process');
        $cleanup->handleEndProcess();
        $socket->sendFinish();
    }
} catch (\UmbrellaSocketException $e) {
    // $cleanup->handleDatabase();
    // $cleanup->handleEndProcess();
} catch (\UmbrellaPreventMaxExecutionTime $e) {
    $socket->sendLog('UmbrellaPreventMaxExecutionTime: ' . $e->getMessage(), true);
    $finish = false;
    $socket->sendPreventMaxExecutionTime($e->getCursor());
} catch (\UmbrellaDatabasePreventMaxExecutionTime $e) {
    $socket->sendLog('UmbrellaDatabasePreventMaxExecutionTime: ' . $e->getMessage(), true);
    $finish = false;
    $socket->sendPreventDatabaseMaxExecutionTime($e->getCursor());
} catch (\UmbrellaInternalRequestException $e) {
    $socket->sendLog('Internal Exception Error: ' . $e->getMessage(), true);
    // $cleanup->handleEndProcess();
} catch (\UmbrellaException $e) {
    $socket->sendLog('Error: ' . $e->getMessage(), true);
    $socket->sendError($e);
    // $cleanup->handleDatabase();
    // $cleanup->handleEndProcess();
} catch (\Exception $e) {
    $socket->sendLog('Unknown Exception Error: ' . $e->getMessage(), true);
    $socket->sendError(new UmbrellaException($e->getMessage(), 'unknown_error', true));
    // $cleanup->handleDatabase();
    // $cleanup->handleEndProcess();
} finally {
    $socket->sendLog('Finally: Close connection');

    if ($connection !== null) {
        $connection->close();
    }

    if (isset($socket) && $socket instanceof UmbrellaWebSocket) {
        sleep(3); // Wait for the last message to be sent
        $socket->close();
    }

    $errorHandler->unregister();

    unset($totalFilesSent, $startTimer, $safeTimeLimit);

    if ($finish) {
        $socket->sendLog('Finally: is finish');
        $cleanup->handleDatabase();
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
