<?php

if (!class_exists('UmbrellaContext', false)):
    class UmbrellaContext
    {
        const SUFFIX = 'umb_database';

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
            'mp4',
            'bak',
            'tmp',
            'log',
            'mmdb',
            'mdb',
            'mov'
        ];

        const DEFAULT_DIRECTORY_EXCLUDED = [
            '.quarantine',
            '.duplicacy',
            '.tmb',
            '.wp-cli',
            'php_errorlog',
            DIRECTORY_SEPARATOR . 'cgi-bin',
            DIRECTORY_SEPARATOR . 'cache',
            DIRECTORY_SEPARATOR . 'lscache',
            DIRECTORY_SEPARATOR . 'rb-plugins',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'litespeed', // Take care of this one
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'upgrade',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'updraft',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'ai1wm-backups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'aiowps_backups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'wpvividbackup',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'error_log',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'et-cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'nginx_cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'wpdm-cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ShortpixelBackups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'et_temp',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'wphb-cache',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'backups',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'backup',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'nfwlog',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'wflogs',
            DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'webtoffee_iew_log',
        ];

        const DEFAULT_EXCLUDE_FILES = [
            '.',
            '..',
            '.DS_Store',
            'cloner.php',
            'cloner_error_log',
            'restore_error_log',
            'error_log'
        ];

        const DEFAULT_FILE_SIZE_LIMIT = 50 * 1024 * 1024; // 50 Mo

        protected $baseDirectory;

        protected $tables;

        protected $databasePrefix;

        protected $incrementalDate;

        protected $options;

        protected $requestId;

        protected $fileCursor;

        protected $databaseDumpCursor;

        protected $databaseCursor;

        protected $scanCursor;

        protected $internalRequest;

        protected $retryFromWebsocketServer;

        protected $rootDirectory;

        protected $signedUrl;

        public function __construct($params)
        {
            $this->baseDirectory = rtrim($params['baseDirectory'], DIRECTORY_SEPARATOR);
            $this->tables = $params['tables'] ?? [];
            $this->databasePrefix = $params['database_prefix'] ?? '';
            $this->incrementalDate = isset($params['incremental_date']) ? strtotime($params['incremental_date']) : null;
            $this->options = [
                'file_size_limit' => $params['options']['file_size_limit'] ?? self::DEFAULT_FILE_SIZE_LIMIT,
                'excluded_extension' => $params['options']['excluded_extension'] ? array_merge(self::DEFAULT_EXTENSION_EXCLUDED, $params['excluded_extension']) : self::DEFAULT_EXTENSION_EXCLUDED,
                'excluded_directories' => [],
                'excluded_files' => []
            ];
            $this->requestId = $params['requestId'];
            $this->fileCursor = $params['fileCursor'];
            $this->databaseDumpCursor = $params['databaseDumpCursor'];
            $this->databaseCursor = $params['databaseCursor'];
            $this->scanCursor = $params['scanCursor'];
            $this->internalRequest = $params['internalRequest'] ?? false;
            $this->retryFromWebsocketServer = $params['retryFromWebsocketServer'] ?? false;

            $this->setExcludedFiles($params);
            $this->setExcludedDirectories($params);
            $this->setupRootDirectory();
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

        public function getInternalRequest()
        {
            return $this->internalRequest;
        }

        public function getFileCursor()
        {
            return $this->fileCursor;
        }

        public function getScanCursor()
        {
            return $this->scanCursor;
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

        public function getDatabasePrefix()
        {
            return $this->databasePrefix;
        }

        public function getRootDatabaseBackupDirectory()
        {
            return $this->rootDirectory;
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

        public function getDictionaryPath()
        {
            return  sprintf('%s' . DIRECTORY_SEPARATOR . '%s-dictionary.php', $this->getBaseDirectory(), $this->getRequestId());
        }

        public function getDirectoryDictionaryPath()
        {
            return  sprintf('%s' . DIRECTORY_SEPARATOR . '%s-directory-dictionary.php', $this->getBaseDirectory(), $this->getRequestId());
        }

        public function getIncrementalDate()
        {
            return $this->incrementalDate;
        }
    }
endif;
