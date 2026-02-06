<?php

if (!class_exists('UmbrellaScanBackup', false)):
    class UmbrellaScanBackup
    {
        use UmbrellaProcessCapacityTrait;

        protected $directoryDictionaryHandle;

        protected $filesDictionaryHandle;

        protected $siteChecksumDirectoryGenerator;

        protected $checksumDictionaryGenerator;

        protected $context;

        protected $socket;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;

            $this->checksumDictionaryGenerator = new UmbrellaChecksumDictionaryGenerator($params);
            $this->siteChecksumDirectoryGenerator = new UmbrellaSiteChecksumDirectoryGenerator($params);
        }

        protected function getContext()
        {
            return $this->context;
        }

        public function __destruct()
        {
            $this->checksumDictionaryGenerator->closeChecksumFile();
            $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();
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

        /**
         * Detects loops in paths (repeating patterns)
         * @param string $path
         * @return bool
         */
        protected function hasPathLoop($path)
        {
            // Clean the path
            $path = trim($path, DIRECTORY_SEPARATOR);

            if (empty($path)) {
                return false;
            }

            $segments = explode(DIRECTORY_SEPARATOR, $path);
            $segmentCount = count($segments);

            // If less than 6 segments, no significant loop risk
            if ($segmentCount < 6) {
                return false;
            }

            // Check for repeating patterns
            for ($patternLength = 1; $patternLength <= 4; $patternLength++) {
                if ($this->hasRepeatingPattern($segments, $patternLength)) {
                    return true;
                }
            }

            // Check if the same segment appears more than 5 times
            $segmentCounts = array_count_values($segments);
            foreach ($segmentCounts as $count) {
                if ($count > 5) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Checks if a pattern of segments repeats in the array
         * @param array $segments
         * @param int $patternLength
         * @return bool
         */
        protected function hasRepeatingPattern($segments, $patternLength)
        {
            $segmentCount = count($segments);

            // Need at least 3 pattern repetitions to be considered a loop
            $minRepeats = 3;
            $minSegmentsNeeded = $patternLength * $minRepeats;

            if ($segmentCount < $minSegmentsNeeded) {
                return false;
            }

            // Look for repetitive patterns starting from the end of the path
            for ($start = $segmentCount - $minSegmentsNeeded; $start >= 0; $start--) {
                $pattern = array_slice($segments, $start, $patternLength);
                $repeatCount = 1;

                // Check how many times this pattern repeats consecutively
                for ($i = $start + $patternLength; $i + $patternLength <= $segmentCount; $i += $patternLength) {
                    $nextPattern = array_slice($segments, $i, $patternLength);
                    if ($pattern === $nextPattern) {
                        $repeatCount++;
                    } else {
                        break;
                    }
                }

                // If the pattern repeats at least 3 times, it's probably a loop
                if ($repeatCount >= $minRepeats) {
                    return true;
                }
            }

            return false;
        }

        public function writeDirectoriesForChecksumIntegrity($filePath, $options = [
            'write_checksum_integrity' => false,
            'flag_updated_files' => false
        ])
        {
            $this->checksumDictionaryGenerator->startDirectory();

            $dirIteratorForChecksumIntegrity = new DirectoryIterator($filePath);
            foreach ($dirIteratorForChecksumIntegrity as $fileChecksumDirectory) {
                if ($fileChecksumDirectory->isDot()) {
                    continue;
                }

                if ($this->isDir($fileChecksumDirectory)) {
                    continue;
                }

                $filePathChecksumIntegrity = $fileChecksumDirectory->getPathname();

                if (!$this->checkProcessFile($filePathChecksumIntegrity)) {
                    continue;
                }

                if ($options['flag_updated_files'] && $this->canProcessIncrementalFile($filePathChecksumIntegrity)) {
                    $lineDirectory = $filePath . ':FILE_CHANGED';

                    $this->siteChecksumDirectoryGenerator->addDirectory($lineDirectory);
                    return;
                }

                $this->checksumDictionaryGenerator->addFile($fileChecksumDirectory->getFilename());
            }

            $checksumValue = $this->checksumDictionaryGenerator->getChecksumValue();
            if (empty($checksumValue)) {
                return;
            }

            $this->siteChecksumDirectoryGenerator->addDirectory($filePath, $checksumValue);
        }

        public function writeDirectoriesForSize($filePath, $options = [
            'full_directory_scan' => false // = true, we scan the full directory, without skipping any files
        ])
        {
            $totalSize = 0;

            $dirIteratorForChecksumIntegrity = new DirectoryIterator($filePath);
            foreach ($dirIteratorForChecksumIntegrity as $fileChecksumDirectory) {
                if ($fileChecksumDirectory->isDot()) {
                    continue;
                }

                if ($this->isDir($fileChecksumDirectory)) {
                    continue;
                }

                $filePathChecksumIntegrity = $fileChecksumDirectory->getPathname();

                if (!$options['full_directory_scan'] && !$this->checkProcessFile($filePathChecksumIntegrity)) {
                    continue;
                }

                $totalSize += @filesize($filePathChecksumIntegrity);
            }

            $this->siteChecksumDirectoryGenerator->addDirectorySize($filePath, $totalSize);
        }

        public function scanAllDirectories($options = ['write_checksum_integrity' => false, 'flag_updated_files' => false, 'write_size' => false, 'full_directory_scan' => false])
        {
            if ($this->context === null || $this->socket === null) {
                $this->socket->sendLog('[scanAllDirectories] no context or no socket');
                return;
            }

            try {
                $dirIterator = new RecursiveDirectoryIterator(
                    $this->context->getBaseDirectory(),
                    RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                );
                $filterIterator = new ReadableRecursiveFilterIterator($dirIterator);
                $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);

                // Limit recursion depth to maximum 40 levels
                $iterator->setMaxDepth(40);
            } catch (Exception $e) {
                throw new UmbrellaException('Could not open directory: ' . $this->context->getBaseDirectory(), 'directory_open_failed');
            }

            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $startProcessing = false;

            $lineNumber = 0;

            if ($this->context->getScanDirectoryCursor() === 0) {
                $this->siteChecksumDirectoryGenerator->startDirectory();

                // Add the root directory itself (not included in the iterator)
                if ($options['write_checksum_integrity']) {
                    $this->writeDirectoriesForChecksumIntegrity($this->context->getBaseDirectory(), $options);
                } elseif ($options['write_size']) {
                    $this->writeDirectoriesForSize($this->context->getBaseDirectory(), $options);
                } else {
                    $this->siteChecksumDirectoryGenerator->addDirectory($this->context->getBaseDirectory());
                }
            }

            foreach ($iterator as $fileInfo) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();
                    // In that case, it's important to set the line number to the scan directory cursor
                    if ($lineNumber < $this->context->getScanDirectoryCursor()) {
                        $lineNumber = $this->context->getScanDirectoryCursor();
                    }
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                if (!$startProcessing && $lineNumber >= $this->context->getScanDirectoryCursor()) {
                    $startProcessing = true; // Find the cursor, start processing from the next file
                }

                $lineNumber++;

                if (!$startProcessing) {
                    continue;
                }

                try {
                    $filePath = $fileInfo->getPathname();

                    $relativePath = str_replace($this->context->getBaseDirectory(), '', $filePath);

                    $this->socket->sendScanDirectoryCursor($lineNumber);  // Directory cursor correspond to the line number in the directory dictionary during scan process

                    // Protection against repeating patterns in the path
                    if ($this->hasPathLoop($relativePath)) {
                        continue;
                    }

                    if (!$this->isDir($fileInfo)) {
                        continue;
                    }

                    if (!$this->canProcessDirectory($filePath)) {
                        continue;
                    }

                    if ($options['write_checksum_integrity']) {
                        $this->writeDirectoriesForChecksumIntegrity($filePath, $options);
                    } elseif ($options['write_size']) {
                        $this->writeDirectoriesForSize($filePath, $options);
                    } else {
                        $this->siteChecksumDirectoryGenerator->addDirectory($filePath);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();

            $this->socket->sendTelemetryCounter('backup.scan.directories-checksum-finished', [
                'request_id' => $this->context->getRequestId(),
                'type' => 'directory',
                'count' => $lineNumber
            ]);

            return true;
        }
    }
endif;
