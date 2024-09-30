<?php

if (!class_exists('UmbrellaFileBackup', false)):
    class UmbrellaFileBackup extends UmbrellaAbstractProcessBackup
    {
        protected $extensionExcluded;

        protected $fileSizeLimit;

        protected $filesExcluded;

        public function __construct($params)
        {
            parent::__construct($params);

            $this->extensionExcluded = $this->context->getExtensionExcluded();
            $this->fileSizeLimit = $this->context->getFileSizeLimit();
            $this->filesExcluded = $this->context->getFilesExcluded();
        }

        public function __destruct()
        {
            $this->closeDictionaries();
        }

        protected function hasWordPressInSubfolder($directory)
        {
            $indexFile = $directory . '/index.php';

            if (!file_exists($indexFile)) {
                return false;
            }

            $indexText = @file_get_contents($indexFile);

            $searchFor = '/wp-blog-header.php';

            if (stripos($indexText, $searchFor) === false) {
                return false;
            }

            return true;
        }

        protected function canProcessDirectory($directory)
        {
            if(!file_exists($directory)) {
                return false;
            }

            $directoriesExcluded = $this->context->getDirectoriesExcluded();
            $dirnameForFilepath = trim(str_replace($this->context->getBaseDirectory(), '', $directory));

            // Check if the directory is in the excluded directories without in_array
            foreach ($directoriesExcluded as $dir) {
                if (strpos($dirnameForFilepath, $dir) !== false) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param string $filePath
         * @return bool
         */
        protected function canProcessFile($filePath, $options = [])
        {
            if(!file_exists($filePath)) {
                return false;
            }

            // filepath contain dictionary.php ; we sent this manually
            if(strpos($filePath, 'dictionary.php') !== false) {
                return false;
            }

            if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), $this->extensionExcluded)) {
                return false;
            }

            if (@filesize($filePath) >= $this->fileSizeLimit) {
                return false;
            }

            if(in_array(basename($filePath), $this->filesExcluded)) {
                return false;
            }

            return true;
        }

        protected function canProcessIncrementalFile($filePath)
        {
            $incrementalDate = $this->context->getIncrementalDate();

            // If the file is older than the incremental date, we skip it
            if($incrementalDate !== null && @filemtime($filePath) < $incrementalDate) {
                return false;
            }

            return true;
        }

        protected function isDir($fileInfo)
        {
            try {
                return $fileInfo->isDir();
            } catch(Exception $e) {
                return false;
            }
        }

        public function backup()
        {
            if($this->context === null || $this->socket === null) {
                return;
            }

            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $lineNumber = 0;
            $startProcessing = false;
            $pendingFiles = [];
            $activeFibers = [];

            while (($line = fgets($this->directoryDictionaryHandle)) !== false) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->closeDictionaries();
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                // Start by */ to end the dictionary
                if (strpos($line, '*/') !== false) {
                    break;
                }

                if (!$startProcessing && $lineNumber >= $this->context->getFileCursor()) {
                    $startProcessing = true; // Find the cursor, start processing from the next file
                }

                $lineNumber++;

                if (!$startProcessing) {
                    continue;
                }
                $directory = trim($line);

                if (file_exists($directory)) {
                    $dirIterator = new DirectoryIterator($directory);

                    $this->socket->sendFileCursor($lineNumber); // File cursor correspond to the line number in the directory dictionary

                    foreach ($dirIterator as $fileInfo) {
                        if ($fileInfo->isDot()) {
                            continue;
                        }

                        if($this->isDir($fileInfo)) {
                            continue;
                        }

                        $filePath = $fileInfo->getPathname();

                        if (!$this->canProcessFile($filePath)) {
                            continue; // Skip because we can't process the file
                        }

                        if(!$this->canProcessIncrementalFile($filePath)) {
                            continue;
                        }

                        $this->socket->send($filePath);
                        $totalFilesSent++;
                    }
                }
            }

            return true;
        }

        public function backupDirectory($directory)
        {
            if($this->context === null || $this->socket === null) {
                return;
            }
            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $startProcessing = false;

            try {
                $dirIterator = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
                $filterIterator = new ReadableRecursiveFilterIterator($dirIterator);
                $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);
            } catch (Exception $e) {
                throw new UmbrellaException('Could not open directory: ' . $directory, 'directory_open_failed');
            }

            foreach ($dirIterator as $fileInfo) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    throw new UmbrellaInternalRequestException();
                    break; // Stop if we are close to the time limit
                }

                $filePath = $fileInfo->getPathname();

                if ($this->isDir($fileInfo)) {
                    $this->backupDirectory($filePath);
                }

                if($this->hasWordPressInSubfolder($filePath)) {
                    continue;
                }

                if(!$this->canProcessDirectory($filePath)) {
                    continue;
                }

                $filePath = trim($filePath);

                if ($this->canProcessFile($filePath)) {
                    $this->socket->send($filePath);

                    $totalFilesSent++;
                }
            }

            return true;
        }
    }
endif;
