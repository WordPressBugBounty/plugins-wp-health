<?php

if (!class_exists('UmbrellaFileBackup', false)):
    class UmbrellaFileBackup extends UmbrellaAbstractProcessBackup
    {
        use UmbrellaProcessCapacityTrait;

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

            $this->siteChecksumDirectoryGenerator->rewind();

            while (($line = $this->siteChecksumDirectoryGenerator->getNextLine()) !== false) {
                $currentTime = time();

                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->closeDictionaries();
                    $this->socket->sendLog('During while: throw UmbrellaPreventMaxExecutionTime');
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break; // Stop if we are close to the time limit
                }

                if (!$startProcessing && $lineNumber >= $this->context->getFileCursor()) {
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

                $this->checksumDictionaryGenerator->startDirectory();

                $directoryPath = $this->context->getBaseDirectory() . $directory;

                if (file_exists($directoryPath)) {
                    $dirIterator = new DirectoryIterator($directoryPath);
                    $this->socket->sendFileCursor($lineNumber); // File cursor correspond to the line number in the directory dictionary

					$skipping = false;
					if ($lineNumber === $this->context->getFileCursor() && !empty($lastProcessedFilename)) {
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

                        $currentTime = time();

                        if (($currentTime - $startTimer) >= $safeTimeLimit) {
                            $this->closeDictionaries();
                            $this->socket->sendLog('During while directory fileinfo: throw UmbrellaPreventMaxExecutionTime');
                            throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                            break; // Stop if we are close to the time limit
                        }

                        $filePath = $fileInfo->getPathname();

                        if (!$this->checkProcessFile($filePath)) {
                            continue;
                        }

						if ($skipping) {
							if ($fileInfo->getFilename() === basename($this->context->getFileName())) {
								$skipping = false;
								$this->socket->sendLog('Found last processed file, resuming...');
							}
							continue;
						}

                        $this->checksumDictionaryGenerator->addFile($fileInfo->getFilename());

                        if (!$this->canProcessIncrementalFile($filePath)) {
                            continue;
                        }
                        $this->socket->send($filePath);
                        $totalFilesSent++;
                    }

                    // Don't send the full path, only the relative path
                    $this->checksumDictionaryGenerator->endDirectory($directoryPath);
                }
            }

            return true;
        }

        protected function getContext()
        {
            return $this->context;
        }
    }
endif;
