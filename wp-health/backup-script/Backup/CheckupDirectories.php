<?php
if (!class_exists('UmbrellaCheckupDirectories', false)):
    class UmbrellaCheckupDirectories
    {
        use UmbrellaProcessCapacityTrait;
        /**
         * @var int
         */
        protected $cursor;

        /**
         * @var string[]
         */
        protected $checkupDirectories;

        /**
         * @var UmbrellaContext
         */
        protected $context;

        /**
         * @var UmbrellaWebSocket
         */
        protected $socket;

        /**
         * @param array $params
         */
        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;

            $this->checkupDirectories = $this->context->getCheckupDirectories();
            $this->cursor = $this->context->getCheckupDirectoriesCursor();
        }

        /**
         * @return bool
         */
        public function check()
        {
            if ($this->context === null || $this->socket === null) {
                return;
            }
            global $startTimer, $totalFilesSent, $safeTimeLimit;

            foreach ($this->checkupDirectories as $index => $originalDirectory) {
                $checkupDirectory = $this->context->getBaseDirectory() . DIRECTORY_SEPARATOR . $originalDirectory;
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->socket->sendLog('During while: throw UmbrellaPreventMaxExecutionTime');
                    throw new UmbrellaPreventMaxExecutionTime($index);
                    break; // Stop if we are close to the time limit
                }

                if ($index < $this->cursor) {
                    $this->socket->sendLog('checkupDirectories cursor excluded: ' . $checkupDirectory);
                    continue;
                }

                if (!file_exists($checkupDirectory)) {
                    $this->socket->sendLog('checkupDirectories does not exist: ' . $checkupDirectory);
                    continue;
                }

                $dirIterator = new DirectoryIterator($checkupDirectory);
                $this->socket->sendStartCheckupDirectory($originalDirectory);

                $lastProcessedFilename = $this->context->getLastProcessedFilename();
                $skipping = false;
                if ($index === $this->cursor && !empty($lastProcessedFilename)) {
                    $skipping = true;
                    $this->socket->sendLog('Resuming from file: ' . $lastProcessedFilename);
                }

                foreach ($dirIterator as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }

                    if ($this->isDir($fileInfo)) {
                        continue;
                    }

                    $filePath = $fileInfo->getPathname();

                    if (!$this->checkProcessFile($filePath)) {
                        continue;
                    }

                    $this->socket->sendFileCheckupDirectory($fileInfo->getFilename());

                    if ($skipping) {
                        if ($fileInfo->getFilename() === basename($lastProcessedFilename)) {
                            $skipping = false;
                            $this->socket->sendLog('Found last processed file, resuming...');
                        }
                        continue;
                    }

                    $this->socket->send($filePath);
                }

                $this->getContext()->setLastProcessedFilename('');
                $this->socket->sendEndCheckupDirectory($originalDirectory);
            }
            return true;
        }

        protected function isDir($fileInfo)
        {
            try {
                return $fileInfo->isDir();
            } catch (Exception $e) {
                return false;
            }
        }

        protected function getContext()
        {
            return $this->context;
        }
    }
endif;
