<?php

if (!class_exists('UmbrellaSiteChecksumDirectoryGenerator', false)):
    class UmbrellaSiteChecksumDirectoryGenerator
    {
        /**
         * @var UmbrellaContext
         */
        protected $context;

        /**
         * @var UmbrellaWebSocket
         */
        protected $socket;

        protected $siteChecksumDirectoryHandler;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;
            $this->openSiteChecksumDirectoryHandler();
        }

        public function closeSiteChecksumDirectoryHandler()
        {
            if (!$this->siteChecksumDirectoryHandler || !is_resource($this->siteChecksumDirectoryHandler)) {
                return;
            }

            fclose($this->siteChecksumDirectoryHandler);
            $this->siteChecksumDirectoryHandler = null;
        }

        public function openSiteChecksumDirectoryHandler()
        {
            $path = $this->context->getDirectoryDictionaryPath();
            $dir = dirname($path);

            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $fopenError = null;
            set_error_handler(function ($errno, $errstr) use (&$fopenError) {
                $fopenError = $errstr;
                return true;
            });
            $handle = fopen($path, 'a+');
            restore_error_handler();

            if ($handle === false) {
                throw new \UmbrellaException(
                    sprintf(
                        'Cannot open directory dictionary file: %s (dir_exists: %s, php error: %s)',
                        $path,
                        is_dir($dir) ? 'yes' : 'no',
                        $fopenError ?? 'none'
                    ),
                    'directory_dictionary_open_failed'
                );
            }

            $this->siteChecksumDirectoryHandler = $handle;
        }

        public function __destruct()
        {
            $this->closeSiteChecksumDirectoryHandler();
        }

        public function startDirectory()
        {
            if (!$this->siteChecksumDirectoryHandler) {
                $this->socket->sendLog('[SiteChecksumDirectoryGenerator] startDirectory skipped: handler not open', true);
                return;
            }

            fwrite($this->siteChecksumDirectoryHandler, "<?php if(!defined('UMBRELLA_BACKUP_KEY')){  exit; } ?>" . PHP_EOL);
        }

        public function addDirectory(string $path, string $checksumValue = '')
        {
            if (!$this->siteChecksumDirectoryHandler) {
                $this->socket->sendLog('[SiteChecksumDirectoryGenerator] addDirectory skipped: handler not open', true);
                return;
            }

            $path = str_replace($this->context->getBaseDirectory(), '', $path);
            if (empty($path)) {
                $path = '/';
            }

            $lineDirectory = $path;
            if (!empty($checksumValue)) {
                $lineDirectory .= ';' . $checksumValue;
            }

            fwrite($this->siteChecksumDirectoryHandler, "$lineDirectory" . PHP_EOL);
        }

        public function addDirectorySize(string $path, $size = 0)
        {
            if (!$this->siteChecksumDirectoryHandler) {
                $this->socket->sendLog('[SiteChecksumDirectoryGenerator] addDirectorySize skipped: handler not open', true);
                return;
            }

            $path = str_replace($this->context->getBaseDirectory(), '', $path);
            if (empty($path)) {
                $path = '/';
            }

            $lineDirectory = $path . ';' . $size;

            fwrite($this->siteChecksumDirectoryHandler, "$lineDirectory" . PHP_EOL);
        }

        public function getHandler()
        {
            return $this->siteChecksumDirectoryHandler;
        }

        public function rewind()
        {
            if ($this->siteChecksumDirectoryHandler) {
                rewind($this->siteChecksumDirectoryHandler);
            }
        }

        public function getNextLine()
        {
            if (!$this->siteChecksumDirectoryHandler) {
                return false;
            }
            return fgets($this->siteChecksumDirectoryHandler);
        }
    }
endif;
