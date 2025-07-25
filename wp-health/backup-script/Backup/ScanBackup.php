<?php

if (!class_exists('UmbrellaScanBackup', false)):
    class UmbrellaScanBackup
    {
        protected $directoryDictionaryHandle;

        protected $filesDictionaryHandle;

        protected $context;

        protected $socket;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;

            $this->createDefaultDirectoryDictionary();
            $this->createDefaultFilesDictionary();

            $this->filesDictionaryHandle = fopen($this->context->getDictionaryPath(), $params['dictionary_mode'] ?? 'a');
            $this->directoryDictionaryHandle = fopen($this->context->getDirectoryDictionaryPath(), $params['dictionary_mode'] ?? 'a');
        }

        protected function createDefaultFilesDictionary()
        {
            $dictionaryPath = $this->context->getDictionaryPath();
            if ($this->context->getScanCursor() === 0) {
                file_put_contents($dictionaryPath, '<?php if(!defined(\'UMBRELLA_BACKUP_KEY\')){  exit; } /*' . PHP_EOL . '["dictionary.json"');
            }
        }

        protected function createDefaultDirectoryDictionary()
        {
            $directoryDictionaryPath = $this->context->getDirectoryDictionaryPath();
            if (!file_exists($directoryDictionaryPath)) {
                file_put_contents($directoryDictionaryPath, '<?php if(!defined(\'UMBRELLA_BACKUP_KEY\')){  exit; } /*' . PHP_EOL);
            }
        }

        public function __destruct()
        {
            $this->closeDirectoryDictionary();
            $this->closeFilesDictionary();
        }

        public function changeModeDirectoryDictionary($mode)
        {
            $this->closeDirectoryDictionary();
            $this->directoryDictionaryHandle = fopen($this->context->getDirectoryDictionaryPath(), $mode);
        }

        /**
         * Close the directory dictionary
         */
        public function closeDirectoryDictionary()
        {
            if ($this->directoryDictionaryHandle === null) {
                return;
            }

            if (!is_resource($this->directoryDictionaryHandle)) {
                return;
            }

            fclose($this->directoryDictionaryHandle);
        }

        /**
         * Close the files directory
         */
        public function closeFilesDictionary()
        {
            if ($this->filesDictionaryHandle === null) {
                return;
            }

            if (!is_resource($this->filesDictionaryHandle)) {
                return;
            }

            fclose($this->filesDictionaryHandle);
        }

        /**
         * Write a line to the directory dictionary
         */
        protected function writeToDirectoryDictionary($line)
        {
            fwrite($this->directoryDictionaryHandle, $line . PHP_EOL);
        }

        /**
         * Write a line to the directory dictionary
         */
        public function closeWriteDirectoryDictionary()
        {
            fwrite($this->directoryDictionaryHandle, '*/');
            $this->closeDirectoryDictionary();
        }

        /**
         * Get the last line from the dictionary
         */
        public function getLastLineFromDictionary($dictionaryPath)
        {
            $fp = fopen($dictionaryPath, 'rb');

            fseek($fp, -1, SEEK_END);

            $lastLine = '';
            $chunk = '';

            while (ftell($fp) > 0) {
                $seek = min(1024, ftell($fp));
                fseek($fp, -$seek, SEEK_CUR);
                $chunk = fread($fp, $seek) . $chunk;
                fseek($fp, -$seek, SEEK_CUR);

                $pos = strrpos($chunk, "\n");
                if ($pos !== false) {
                    $lastLine = substr($chunk, $pos + 1);
                    break;
                }
            }

            fclose($fp);

            return rtrim($lastLine, " \t\n\r\0\x0B?>");
        }

        public function scanTables($tables)
        {
            foreach ($tables as $key => $table) {
                $filePath = $this->context->getRootDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . $table['name'] . '.sql';
                $relativePath = str_replace($this->context->getBaseDirectory(), '', $filePath);

                $this->writeToFilesDictionary($relativePath);
            }
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
            if (!file_exists($directory)) {
                return false;
            }

            $directoriesExcluded = $this->context->getDirectoriesExcluded();
            $dirnameForFilepath = trim(str_replace($this->context->getBaseDirectory(), '', $directory));

            // Check if the directory is in the excluded directories without in_array
            foreach ($directoriesExcluded as $dir) {
                // Check if the directory name matches exactly with the excluded directory
                $cleanDir = DIRECTORY_SEPARATOR . ltrim($dir, DIRECTORY_SEPARATOR);
                $cleanDirname = DIRECTORY_SEPARATOR . ltrim($dirnameForFilepath, DIRECTORY_SEPARATOR);

                // Check with separator at start
                if (strpos($cleanDirname, $cleanDir) === 0) {
                    return false;
                }

                // Check without separator at start
                $cleanDirNoSep = rtrim(ltrim($dir, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
                $cleanDirnameNoSep = rtrim(ltrim($dirnameForFilepath, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);

                if (strpos($cleanDirnameNoSep, $cleanDirNoSep) === 0) {
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
            if (!file_exists($filePath)) {
                return false;
            }

            // filepath contain dictionary.php ; we send this manually
            if (strpos($filePath, 'dictionary.php') !== false) {
                return false;
            }

            if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), $this->context->getExtensionExcluded())) {
                return false;
            }

            if (@filesize($filePath) >= $this->context->getFileSizeLimit()) {
                return false;
            }

            if (in_array(basename($filePath), $this->context->getFilesExcluded())) {
                return false;
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

        public function scanOnlyDirectories($options = [])
        {
            try {
                $dirIterator = new RecursiveDirectoryIterator($this->context->getBaseDirectory(), RecursiveDirectoryIterator::SKIP_DOTS);
                $filterIterator = new ReadableRecursiveFilterIterator($dirIterator);
                $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);
            } catch (Exception $e) {
                throw new UmbrellaException('Could not open directory: ' . $this->context->getBaseDirectory(), 'directory_open_failed');
            }

            $lastLine = $this->getLastLineFromDictionary($this->context->getDirectoryDictionaryPath());

            $this->writeToDirectoryDictionary($this->context->getBaseDirectory());

            $canScan = empty($lastLine); // If the last line is empty, we can scan directly

            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $startProcessing = false;

            $lineNumber = 0;
            foreach ($iterator as $fileInfo) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->closeFilesDictionary();
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                try {
                    $filePath = $fileInfo->getPathname();

                    if (!$canScan && $filePath === $lastLine) {
                        $canScan = true;
                        continue;
                    }

                    if (!$canScan) {
                        continue;
                    }

                    if (!$this->isDir($fileInfo)) {
                        continue;
                    }

                    if ($this->hasWordPressInSubfolder($filePath)) {
                        continue;
                    }

                    if (!$this->canProcessDirectory($filePath)) {
                        continue;
                    }

                    $lineNumber++;

                    $this->writeToDirectoryDictionary($filePath);
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->closeWriteDirectoryDictionary();

            return true;
        }

        public function scanAndCreateDictionary()
        {
            if ($this->context === null || $this->socket === null) {
                return;
            }

            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $lineNumber = 0;
            $startProcessing = false;

            while (($line = fgets($this->directoryDictionaryHandle)) !== false) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->closeDirectoryDictionary();
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                // Start by */ to end the dictionary
                if (strpos($line, '*/') !== false) {
                    break;
                }

                if (!$startProcessing && $lineNumber >= $this->context->getScanCursor()) {
                    $startProcessing = true; // Find the cursor, start processing from the next file
                }

                $lineNumber++;

                if (!$startProcessing) {
                    continue;
                }
                $directory = trim($line);

                if (file_exists($directory)) {
                    $dirIterator = new DirectoryIterator($directory);

                    $this->socket->sendScanCursor($lineNumber);  // File cursor correspond to the line number in the directory dictionary during scan process

                    foreach ($dirIterator as $fileInfo) {
                        if ($fileInfo->isDot()) {
                            continue;
                        }

                        if (!$this->isDir($fileInfo)) {
                            continue;
                        }

                        $filePath = $fileInfo->getPathname();

                        if (!$this->canProcessFile($filePath)) {
                            continue; // Skip because we can't process the file
                        }

                        $relativePath = str_replace($this->context->getBaseDirectory(), '', $filePath);

                        if (!UmbrellaUTF8::seemsUTF8($relativePath)) {
                            $relativePath = UmbrellaUTF8::encodeNonUTF8($relativePath);
                        }

                        // It's important to write the file path to the dictionary before check if it's incremental
                        $this->writeToFilesDictionary($relativePath);
                    }
                }
            }

            $this->closeAndWriteFilesDictionary();

            return true;
        }

        public function closeAndWriteFilesDictionary()
        {
            fwrite($this->filesDictionaryHandle, ']');
            $this->closeFilesDictionary();

            // Remove the first line from the dictionary
            $dictionaryPath = $this->context->getDictionaryPath();
            $dictionaryContent = file_get_contents($dictionaryPath);
            $dictionaryContent = substr($dictionaryContent, strpos($dictionaryContent, '/*') + 2);
            file_put_contents($dictionaryPath, $dictionaryContent);
        }

        /**
        * Write a line to the File dictionary
        */
        protected function writeToFilesDictionary($line)
        {
            fwrite($this->filesDictionaryHandle, ',' . PHP_EOL . '"' . $line . '"');
        }
    }
endif;
