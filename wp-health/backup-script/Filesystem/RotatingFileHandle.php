<?php

if (!class_exists('UmbrellaRotatingFileHandle', false)):
    class UmbrellaRotatingFileHandle
    {
        protected $basePath;
        protected $maxSize;
        protected $callback;
        protected $subDirectoryName;
        protected $promoted = false;

        /** @var UmbrellaFileHandle|null */
        protected $currentHandle = null;
        protected $currentPart = 1;
        protected $currentSize = 0;

        /**
         * @param string $basePath Path to the base file (e.g. /path/to/table.sql)
         * @param int $maxSize Maximum size in bytes before rotation
         * @param callable $callback Callback function to execute on rotation (path) => void
         * @param string|null $subDirectoryName Optional subdirectory name for promotion
         */
        public function __construct($basePath, $maxSize, $callback, $subDirectoryName = null)
        {
            $this->basePath = $basePath;
            $this->maxSize = $maxSize;
            $this->callback = $callback;
            $this->subDirectoryName = $subDirectoryName;

            $this->openNextPart();
        }

        protected function openNextPart()
        {
            $path = $this->getCurrentPath();
            $this->currentHandle = new UmbrellaFileHandle($path, 'wb');
            $this->currentSize = 0;
        }

        protected function getCurrentPath()
        {
            if ($this->currentPart === 1) {
                return $this->basePath;
            }

            $filePath = preg_replace('/\.sql$/', '', $this->basePath);

            return $filePath . '.part' . $this->currentPart . '.sql';
        }

        public function write($data)
        {
            $length = strlen($data);

            if ($this->currentSize + $length > $this->maxSize) {
                $this->rotate();
            }

            $this->currentHandle->write($data);
            $this->currentSize += $length;
        }

        protected function rotate()
        {
            $previousPath = $this->getCurrentPath();
            $this->currentHandle->close();

            if ($this->currentPart === 1 && $this->subDirectoryName && !$this->promoted) {
                $this->promoteToDirectory();
                $previousPath = $this->basePath;
            }

            // Trigger callback for the closed file
            call_user_func($this->callback, $previousPath);

            $this->currentPart++;
            $this->openNextPart();
        }

        protected function promoteToDirectory()
        {
            $directory = dirname($this->basePath) . DIRECTORY_SEPARATOR . $this->subDirectoryName;
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            $newBasePath = $directory . DIRECTORY_SEPARATOR . basename($this->basePath);
            rename($this->basePath, $newBasePath);

            $this->basePath = $newBasePath;
            $this->promoted = true;
        }

        public function close()
        {
            if ($this->currentHandle) {
                $path = $this->getCurrentPath();
                $this->currentHandle->close();
                $this->currentHandle = null;

                // Trigger callback for the last file
                // Only if size > 0? Or always?
                // Usually always to ensure even an empty table has a file or the last chunk is sent.
                // But check if we wrote anything?
                // Creating a new file handle creates the file (mode 'wb').
                // So the file exists.
                call_user_func($this->callback, $path);
            }
        }
    }
endif;
