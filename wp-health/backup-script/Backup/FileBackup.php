<?php

if (!class_exists('UmbrellaFileBackup', false)):
    class UmbrellaFileBackup extends UmbrellaAbstractProcessBackup
    {
        use UmbrellaProcessCapacityTrait;

        const ELIGIBLE_NAMES_PREFIX = 'umb_eligible_';

        const SKIP_TIME_CHECK_EVERY = 2000;

        protected $extensionExcluded;

        protected $fileSizeLimit;

        protected $filesExcluded;

        protected $checksumDictionaryGenerator;

        protected $siteChecksumDirectoryGenerator;

        public function __construct($params)
        {
            parent::__construct($params);

            $this->extensionExcluded = $this->context->getExtensionExcluded();
            $this->fileSizeLimit = $this->context->getFileSizeLimit();
            $this->filesExcluded = $this->context->getFilesExcluded();

            $this->checksumDictionaryGenerator = new UmbrellaChecksumDictionaryGenerator($params);
            $this->siteChecksumDirectoryGenerator = new UmbrellaSiteChecksumDirectoryGenerator($params);
        }

        public function closeDictionaries()
        {
            $this->checksumDictionaryGenerator->closeChecksumFile();
            $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();
        }

        public function __destruct()
        {
            $this->closeDictionaries();
        }

        public function backup()
        {
            if ($this->context === null || $this->socket === null) {
                $this->socket->sendLog('[FileBackup] no context or no socket');
                return;
            }

            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $lineNumber = 0;
            $startProcessing = false;

            $lastProcessedFilename = $this->context->getLastProcessedFilename();
            $resumeInPreviousDirectory = !empty($lastProcessedFilename) && $this->context->getFileCursor() > 1;
            $startCursor = $resumeInPreviousDirectory ? $this->context->getFileCursor() - 1 : $this->context->getFileCursor();

            $this->siteChecksumDirectoryGenerator->rewind();

            while (($line = $this->siteChecksumDirectoryGenerator->getNextLine()) !== false) {
                $currentTime = time();

                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->closeDictionaries();
                    $this->socket->sendLog('During while: throw UmbrellaPreventMaxExecutionTime');
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                if (!$startProcessing && $lineNumber >= $startCursor) {
                    $startProcessing = true; // Find the cursor, start processing from the next file
                }

                $lineNumber++;

                if (!$startProcessing) {
                    continue;
                }

                // $line = [path];[checksum]
                $lineParts = explode(';', $line);
                $path = $lineParts[0];

                if (empty($path)) {
                    continue;
                }

                $directory = trim($path);

                $directoryPath = $this->context->getBaseDirectory() . $directory;

                $skipping = false;
                if ($resumeInPreviousDirectory) {
                    $resumeInPreviousDirectory = false;
                    $skipping = true;
                    $this->socket->sendLog('Resuming from file: ' . $lastProcessedFilename);
                }

                if (file_exists($directoryPath)) {
                    if (!$skipping) {
                        $this->socket->sendFileCursor($lineNumber); // File cursor correspond to the line number in the directory dictionary
                    }

                    $this->checksumDictionaryGenerator->startDirectory();

                    $found = $this->streamDirectory($directoryPath, $lineNumber, $skipping, $lastProcessedFilename);

                    if (!$found) {
                        // The marker vanished between two batches (deleted, renamed). Nothing was
                        // sent, and the files after it would be lost: send the directory again.
                        $this->socket->sendLog('Last processed file not found, restarting directory');
                        $this->checksumDictionaryGenerator->startDirectory();
                        $this->streamDirectory($directoryPath, $lineNumber, false, '');
                    }

                    $this->sendDirectoryChecksum($directoryPath, $lineNumber);
                }
            }

            return true;
        }

        /**
         * Walk one directory and send its eligible files. When resuming, the entries before
         * the last processed file are skipped on their name alone: no stat, no size check, no
         * exclusion test, their eligibility was already recorded by the batch that sent them.
         *
         * @return bool false when resuming and the last processed file was not found
         */
        protected function streamDirectory($directoryPath, $lineNumber, $skipping, $lastProcessedFilename)
        {
            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $eligibleNamesPath = $this->getEligibleNamesPath($lineNumber);

            // Resuming without the record of the previous batches (temporary files purged):
            // classify the skipped entries once so the directory checksum stays complete.
            $classifyWhileSkipping = $skipping && !file_exists($eligibleNamesPath);

            // Without a writable record, the names of this walk stay in the checksum
            // generator, which is what the walk did before. The names recorded by earlier
            // batches, if the file exists, are still read back at the end of the directory.
            $eligibleNames = @fopen($eligibleNamesPath, $skipping ? 'ab' : 'wb');
            if ($eligibleNames === false) {
                $this->socket->sendLog('Cannot write ' . basename($eligibleNamesPath) . ', keeping the names in memory');
            }
            $target = basename($lastProcessedFilename);
            $skipped = 0;

            $dirIterator = new DirectoryIterator($directoryPath);

            foreach ($dirIterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                if ($skipping) {
                    $filename = $fileInfo->getFilename();

                    if ($classifyWhileSkipping && !$this->isDir($fileInfo) && $this->checkProcessFile($fileInfo->getPathname())) {
                        $this->recordEligibleName($eligibleNames, $filename);
                    }

                    if ($filename === $target) {
                        $skipping = false;
                        $this->socket->sendLog('Found last processed file, resuming...');
                    }

                    $skipped++;
                    if (($skipped % self::SKIP_TIME_CHECK_EVERY) === 0 && (time() - $startTimer) >= $safeTimeLimit) {
                        $this->closeEligibleNames($eligibleNames);
                        $this->closeDictionaries();
                        $this->socket->sendLog('During skip: throw UmbrellaPreventMaxExecutionTime');
                        throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    }

                    continue;
                }

                if ($this->isDir($fileInfo)) {
                    continue;
                }

                $currentTime = time();

                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->closeEligibleNames($eligibleNames);
                    $this->closeDictionaries();
                    $this->socket->sendLog('During while directory fileinfo: throw UmbrellaPreventMaxExecutionTime');
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                $filePath = $fileInfo->getPathname();

                if (!$this->checkProcessFile($filePath)) {
                    continue;
                }

                $this->recordEligibleName($eligibleNames, $fileInfo->getFilename());

                if (!$this->canProcessIncrementalFile($filePath)) {
                    continue;
                }
                $this->socket->send($filePath);
                $totalFilesSent++;
            }

            $this->closeEligibleNames($eligibleNames);

            return !$skipping;
        }

        protected function recordEligibleName($eligibleNames, $filename)
        {
            if ($eligibleNames === false) {
                $this->checksumDictionaryGenerator->addFile($filename);
                return;
            }

            fwrite($eligibleNames, $filename . "\n");
        }

        protected function closeEligibleNames($eligibleNames)
        {
            if ($eligibleNames !== false) {
                fclose($eligibleNames);
            }
        }

        protected function getEligibleNamesPath($lineNumber)
        {
            return rtrim($this->context->getChecksumDirectory(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . self::ELIGIBLE_NAMES_PREFIX
                . preg_replace('/[^a-zA-Z0-9]/', '', (string) $this->context->getRequestId())
                . '_' . $lineNumber . '.txt';
        }

        protected function sendDirectoryChecksum($directoryPath, $lineNumber)
        {
            $eligibleNamesPath = $this->getEligibleNamesPath($lineNumber);

            // Names recorded by this batch and the previous ones; without the file, the
            // generator already holds the names of a full walk.
            $handle = @fopen($eligibleNamesPath, 'rb');
            if ($handle !== false) {
                while (($name = fgets($handle)) !== false) {
                    $this->checksumDictionaryGenerator->addFile(rtrim($name, "\n"));
                }
                fclose($handle);
            }

            // Don't send the full path, only the relative path
            $this->checksumDictionaryGenerator->endDirectory($directoryPath);

            @unlink($eligibleNamesPath);
        }

        protected function getContext()
        {
            return $this->context;
        }
    }
endif;
