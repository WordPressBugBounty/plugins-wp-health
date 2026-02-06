<?php

if (!class_exists('UmbrellaContext', false)):
    class UmbrellaContext
    {
        const SUFFIX = 'umb_database';

        const CHECKSUM_SUFFIX = 'umb_checksum';

        const DEFAULT_EXTENSION_EXCLUDED = [
            'gz',
            'zip',
            'tar',
            'tar.gz',
            'tar.bz2',
            'tar.xz',
            'rar',
            '7z',
            'tgz',
            'tbz2',
            'tbz',
            'wpress',
            'raw',
            'bak',
            'tmp',
            'log',
            'mmdb',
            'mdb',
            'daf',
        ];

        const DEFAULT_DIRECTORY_EXCLUDED = [
            DIRECTORY_SEPARATOR . '.git',
            DIRECTORY_SEPARATOR . 'cgi-bin',
            DIRECTORY_SEPARATOR . '.quarantine',
            DIRECTORY_SEPARATOR . '.duplicacy',
            DIRECTORY_SEPARATOR . '.tmb',
            DIRECTORY_SEPARATOR . '.wp-cli',
            DIRECTORY_SEPARATOR . 'php_errorlog',
            DIRECTORY_SEPARATOR . 'cache',
            DIRECTORY_SEPARATOR . '_cache',
            DIRECTORY_SEPARATOR . 'lscache',
            DIRECTORY_SEPARATOR . 'rb-plugins',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'litespeed', // Take care of this one
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'litespeed-cache', // From website with ~20Go of cache
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'upgrade',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'updraft',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'ai1wm-backups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'aiowps_backups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'wpvividbackup',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'error_log',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'et-cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'nginx_cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'wpdm-cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'nitropack-logs',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ShortpixelBackups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'et_temp',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'wphb-cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'backups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'backup',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'nfwlog',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'flying-press',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'wflogs',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'webtoffee_iew_log',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'umbrella-upgrade-temp-backup',
            DIRECTORY_SEPARATOR . 'umb_checksum',
        ];

        const DEFAULT_EXCLUDE_FILES = [
            '.',
            '..',
            '.DS_Store',
            'cloner.php',
            'cloner_error_log',
            'restore_error_log',
            'error_log',
        ];

        const DEFAULT_FILE_SIZE_LIMIT = 500 * 1024 * 1024; // 500 Mo

        protected $baseDirectory;

        protected $tables;

        protected $databasePrefix;

        protected $incrementalDate;

        protected $options;

        protected $requestId;

        protected $fileCursor;

        protected $checkupDirectoriesCursor;

        protected $databaseDumpCursor;

        protected $databaseCursor;

        protected $scanCursor;

        protected $scanDirectoryCursor;

        protected $internalRequest;

        protected $retryFromWebsocketServer;

        protected $rootDirectory;

        protected $checksumDirectory;

        protected $checkupDirectories;

        protected $signedUrl;

        protected $fileSizeLimits;

        protected $ack;

        protected $action;

        protected $lastProcessedFilename;

        protected $intervalBetweenBatch;

        protected $maximumLinesByTableByBatch;

        public function __construct($params)
        {
            $this->action = $params['action'] ?? '';
            $this->baseDirectory = rtrim($params['baseDirectory'], DIRECTORY_SEPARATOR);
            $this->tables = $params['tables'] ?? [];
            $this->databasePrefix = $params['database_prefix'] ?? '';
            $this->incrementalDate = isset($params['incremental_date']) ? strtotime($params['incremental_date']) : null;
            $this->options = [
                'file_size_limit' => $params['options']['file_size_limit'] ?? self::DEFAULT_FILE_SIZE_LIMIT,
                'excluded_extension' => $params['options']['excluded_extension'] ? array_merge(self::DEFAULT_EXTENSION_EXCLUDED, $params['excluded_extension']) : self::DEFAULT_EXTENSION_EXCLUDED,
                'excluded_directories' => [],
                'excluded_files' => [],
                'max_mo_per_file' => $params['options']['max_mo_per_file'] ?? 50,
                'is_sql_partitioned' => $params['options']['is_sql_partitioned'] ?? false,
            ];
            $this->requestId = $params['requestId'];
            $this->fileCursor = $params['fileCursor'];
            $this->checkupDirectoriesCursor = $params['checkupDirectoriesCursor'];
            $this->databaseDumpCursor = $params['databaseDumpCursor'];
            $this->databaseCursor = $params['databaseCursor'];
            $this->scanCursor = $params['scanCursor'];
            $this->scanDirectoryCursor = $params['scanDirectoryCursor'];
            $this->internalRequest = false; //legacy
            $this->retryFromWebsocketServer = $params['retryFromWebsocketServer'] ?? false;
            $this->checkupDirectories = $params['checkupDirectories'] ?? [];
            $this->fileSizeLimits = $params['fileSizeLimits'] ?? ['*' => self::DEFAULT_FILE_SIZE_LIMIT];
            $this->ack = $params['ack'] ?? '';
            $this->lastProcessedFilename = isset($params['last_processed_filename']) ? $params['last_processed_filename'] : '';
            $this->intervalBetweenBatch = $params['interval_between_batch'] ?? 0;
            $this->maximumLinesByTableByBatch = $params['maximum_lines_by_table_by_batch'] ?? [];

            $this->setExcludedFiles($params);
            $this->setExcludedDirectories($params);
            $this->setupRootDirectory();
            $this->setupChecksumDirectory();
        }

        public function getAction()
        {
            return $this->action;
        }

        public function getLastProcessedFilename()
        {
            return $this->lastProcessedFilename;
        }

        public function setLastProcessedFilename($filename)
        {
            $this->lastProcessedFilename = $filename;
            return $this;
        }

        public function setExcludedFiles($params)
        {
            $excludedFiles = $params['options']['excluded_files'] ? array_merge(self::DEFAULT_EXCLUDE_FILES, $params['options']['excluded_files']) : self::DEFAULT_EXCLUDE_FILES;

            $excludedFiles[] = sprintf('%s-dictionary.php', $this->requestId);
            $excludedFiles[] = sprintf('%s-directory-dictionary.php', $this->requestId);
            $this->options['excluded_files'] = $excludedFiles;
            return $this;
        }

        public function setExcludedDirectories($params)
        {
            $excludedDirectories = $params['options']['excluded_directories'] ? array_merge(self::DEFAULT_DIRECTORY_EXCLUDED, $params['options']['excluded_directories']) : self::DEFAULT_DIRECTORY_EXCLUDED;

            // Add the same directories with a leading slash
            $excludedDirectories = array_reduce($excludedDirectories, function ($carry, $item) {
                if ($item[0] !== DIRECTORY_SEPARATOR) {
                    $carry[] = DIRECTORY_SEPARATOR . $item;
                } else {
                    $carry[] = substr($item, 1);
                }
                $carry[] = $item;

                return $carry;
            }, []);

            $this->options['excluded_directories'] = $excludedDirectories;
            return $this;
        }

        public function addExcludedDirectory($directory)
        {
            $this->options['excluded_directories'][] = $directory;
            return $this;
        }

        public function getFileSizeLimits()
        {
            return $this->fileSizeLimits;
        }

        public function getInternalRequest()
        {
            return $this->internalRequest;
        }

        public function getFileCursor()
        {
            return $this->fileCursor;
        }

        public function getCheckupDirectoriesCursor()
        {
            return $this->checkupDirectoriesCursor;
        }

        public function getCheckupDirectories()
        {
            return $this->checkupDirectories;
        }

        /**
         * Used for files
         */
        public function getScanCursor()
        {
            return $this->scanCursor;
        }

        /**
         * Used for directories
         */
        public function getScanDirectoryCursor()
        {
            return $this->scanDirectoryCursor;
        }

        public function hasFileBatchNotStarted()
        {
            return $this->hasFileSendFileNotStarted() && $this->scanCursor === 0;
        }

        public function hasScanDictionaryFilesBatchNotStarted()
        {
            return $this->scanCursor === 0;
        }

        public function hasFileSendFileNotStarted()
        {
            return $this->fileCursor === 1;
        }

        public function getDatabaseCursor()
        {
            return $this->databaseCursor;
        }

        public function getDatabaseDumpCursor()
        {
            return $this->databaseDumpCursor;
        }

        public function getBaseDirectory()
        {
            if (empty($this->baseDirectory)) {
                return DIRECTORY_SEPARATOR;
            }

            return $this->baseDirectory;
        }

        public function getTables()
        {
            return $this->tables;
        }

        public function getRequestId()
        {
            return $this->requestId;
        }

        public function getRetryFromWebsocketServer()
        {
            return $this->retryFromWebsocketServer ? 1 : 0;
        }

        public function getFilesExcluded()
        {
            return $this->options['excluded_files'];
        }

        public function getDirectoriesExcluded()
        {
            return $this->options['excluded_directories'];
        }

        public function getExtensionExcluded()
        {
            return $this->options['excluded_extension'];
        }

        public function getFileSizeLimit()
        {
            return $this->options['file_size_limit'];
        }

        public function getMaxMoPerFile()
        {
            return $this->options['max_mo_per_file'];
        }

        public function getIsSqlPartitioned()
        {
            return $this->options['is_sql_partitioned'];
        }

        public function getDatabasePrefix()
        {
            return $this->databasePrefix;
        }

        public function getRootDatabaseBackupDirectory()
        {
            return $this->rootDirectory;
        }

        public function getChecksumDirectory()
        {
            return $this->checksumDirectory;
        }

        protected function testDirectoryCreation($directory, $filename)
        {
            if (!file_exists($directory) && !mkdir($directory, 0777, true)) {
                return [
                    'code' => 'directory_creation_error',
                    'directory' => $directory
                ];
            }

            $filePath = $directory . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($filePath)) {
                $result = file_put_contents($filePath, 'X');
                if ($result === false) {
                    return [
                        'code' => 'file_creation_error',
                        'directory' => $filePath
                    ];
                }
            }

            @unlink($filePath);

            return [
                'code' => 'success',
                'directory' => $directory
            ];
        }

        public function setupChecksumDirectory()
        {
            $this->checksumDirectory = $this->baseDirectory . DIRECTORY_SEPARATOR . self::CHECKSUM_SUFFIX;
        }

        public function setupRootDirectory()
        {
            // /umb_database
            $rootDirectory = $this->baseDirectory . DIRECTORY_SEPARATOR . self::SUFFIX;
            $filenameTest = 'test.txt';

            try {
                $response = $this->testDirectoryCreation($rootDirectory, $filenameTest);
                if ($response['code'] === 'success') {
                    $this->rootDirectory = $rootDirectory;
                    return;
                }

                // By default, we use the base directory
                $this->rootDirectory = $this->baseDirectory . DIRECTORY_SEPARATOR . self::SUFFIX;
            } catch (Exception $e) {
                if (file_exists($rootDirectory . DIRECTORY_SEPARATOR . $filenameTest)) {
                    unlink($rootDirectory . DIRECTORY_SEPARATOR . $filenameTest);
                }
            }
        }

        public function createBackupDirectoryIfNotExists()
        {
            $this->createDirectoryIfNotExists($this->getRootDatabaseBackupDirectory());
        }

        public function createChecksumDirectoryIfNotExists()
        {
            $this->createDirectoryIfNotExists($this->getChecksumDirectory());
        }

        protected function createDirectoryIfNotExists($directory)
        {
            if (!file_exists($directory)) {
                mkdir($directory);
            }

            // Write .htaccess with deny all
            $htaccess = $directory . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'deny from all');
            }

            // Write index.php
            $index = $directory . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }

        public function getDictionaryPath()
        {
            return  sprintf('%s' . DIRECTORY_SEPARATOR . '%s-dictionary.php', $this->getBaseDirectory(), $this->getRequestId());
        }

        public function getDirectoryDictionaryPath()
        {
            return  sprintf('%s' . DIRECTORY_SEPARATOR . '%s-directories-checksum-dictionary.php', $this->getChecksumDirectory(), $this->getRequestId());
        }

        public function getIncrementalDate()
        {
            return $this->incrementalDate;
        }

        public function getAck()
        {
            return $this->ack;
        }

        public function getIntervalBetweenBatch()
        {
            return $this->intervalBetweenBatch;
        }

        public function getMaximumLinesByTableByBatch()
        {
            return $this->maximumLinesByTableByBatch;
        }
    }
endif;
