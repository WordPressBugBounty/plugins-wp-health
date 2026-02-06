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
        }

        public function openSiteChecksumDirectoryHandler()
        {
            touch($this->context->getDirectoryDictionaryPath());
            $this->siteChecksumDirectoryHandler = fopen($this->context->getDirectoryDictionaryPath(), 'a+');
        }

        public function __destruct()
        {
            $this->closeSiteChecksumDirectoryHandler();
        }

        public function startDirectory()
        {
            fwrite($this->siteChecksumDirectoryHandler, "<?php if(!defined('UMBRELLA_BACKUP_KEY')){  exit; } ?>" . PHP_EOL);
        }

        public function addDirectory(string $path, string $checksumValue = '')
        {
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
