<?php

if (!class_exists('UmbrellaChecksumDictionaryGenerator', false)):
    class UmbrellaChecksumDictionaryGenerator
    {
        /**
         * @var UmbrellaContext
         */
        protected $context;

        /**
         * @var UmbrellaWebSocket
         */
        protected $socket;

        protected $files = [];
        protected $fileCount = 0;

        /**
         * Maximum number of files before switching to temp file mode.
         * 50,000 files * ~100 chars = ~3MB, safe for most PHP configs.
         */
        const MAX_FILES_IN_MEMORY = 30000;

        protected $tempFile = null;
        protected $useFileMode = false;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;
        }

        public function closeChecksumFile()
        {
            $this->cleanupTempFile();
        }

        public function startDirectory()
        {
            $this->cleanupTempFile();
            $this->files = [];
            $this->fileCount = 0;
            $this->useFileMode = false;
            $this->tempFile = null;
        }

        public function addFile(string $path)
        {
            $path = str_replace($this->context->getBaseDirectory(), '', $path);

            // If we exceed memory limit, switch to file mode
            if (!$this->useFileMode && $this->fileCount >= self::MAX_FILES_IN_MEMORY) {
                $this->switchToFileMode();
            }

            if ($this->useFileMode) {
                fwrite($this->tempFile, $path . "\n");
            } else {
                $this->files[] = $path;
            }

            $this->fileCount++;
        }

        protected function switchToFileMode()
        {
            $this->tempFile = tmpfile();
            $this->useFileMode = true;

            // Write existing files to temp file
            foreach ($this->files as $file) {
                fwrite($this->tempFile, $file . "\n");
            }

            // Free memory
            $this->files = [];
        }

        protected function cleanupTempFile()
        {
            if ($this->tempFile !== null) {
                fclose($this->tempFile);
                $this->tempFile = null;
            }
        }

        /**
         * @param string $directory Send the full path of the directory, not the relative path
         */
        public function endDirectory(string $directory)
        {
            $checksumValue = $this->getChecksumValue();

            if (empty($checksumValue)) {
                return;
            }

            $directory = str_replace($this->context->getBaseDirectory(), '', $directory);

            $directory = $directory === '' ? '/' : $directory;

            $this->socket->sendChecksum($directory, $checksumValue);
        }

        public function getChecksumValue()
        {
            if ($this->useFileMode) {
                return $this->getChecksumFromFile();
            }

            if (empty($this->files)) {
                return null;
            }

            return $this->computeChecksum($this->files);
        }

        protected function getChecksumFromFile()
        {
            if ($this->tempFile === null) {
                return null;
            }

            // Read all lines from temp file
            rewind($this->tempFile);
            $files = [];
            while (($line = fgets($this->tempFile)) !== false) {
                $files[] = rtrim($line, "\n");
            }

            if (empty($files)) {
                return null;
            }

            return $this->computeChecksum($files);
        }

        protected function computeChecksum(array $files)
        {
            // The file content was: "header\npath1\npath2\n" (ends with PHP_EOL)
            // explode(PHP_EOL, content) gave: ["header", "path1", "path2", ""]
            // array_shift removed header, leaving: ["path1", "path2", ""]
            // We don't have the header, so we just need to add the trailing empty string
            $content = $files;
            $content[] = ''; // Trailing empty string from explode on content ending with PHP_EOL

            sort($content, SORT_STRING);
            $content = implode('', $content);

            if (empty($content)) {
                return null;
            }

            return md5($content);
        }
    }
endif;
