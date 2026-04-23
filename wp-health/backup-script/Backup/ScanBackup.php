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

            if ($this->isStagingDirectory(basename($directory))) {
                return false;
            }

            $directoriesExcluded = $this->context->getDirectoriesExcluded();
            $dirnameForFilepath = trim(str_replace($this->context->getBaseDirectory(), '', $directory));

            if (UmbrellaDirectoryExclusion::isExcluded($dirnameForFilepath, $directoriesExcluded)) {
                return false;
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
         * Detects loops in paths (repeating patterns caused by symlinks)
         * @param string $path Relative path from base directory
         * @param string $basePath Absolute base directory path
         * @return bool
         */
        protected function hasPathLoop($path, $basePath = '')
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

            // Check for repeating patterns — only a real loop if symlinks are involved
            $hasRepeating = false;
            for ($patternLength = 1; $patternLength <= 4; $patternLength++) {
                if ($this->hasRepeatingPattern($segments, $patternLength)) {
                    $hasRepeating = true;
                    break;
                }
            }

            // Check if the same segment appears more than 5 times
            if (!$hasRepeating) {
                $segmentCounts = array_count_values($segments);
                foreach ($segmentCounts as $count) {
                    if ($count > 5) {
                        $hasRepeating = true;
                        break;
                    }
                }
            }

            // Repeating directory names are legitimate in vendor packages (e.g. phpseclib/phpseclib/phpseclib).
            // Only flag as a loop when a symlink is actually involved in the path.
            if ($hasRepeating) {
                $fullPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
                return $this->pathContainsSymlink($fullPath);
            }

            return false;
        }

        /**
         * Checks if any directory segment in the path is a symlink
         * @param string $fullPath Absolute filesystem path
         * @return bool
         */
        protected function pathContainsSymlink($fullPath)
        {
            $parts = explode(DIRECTORY_SEPARATOR, trim($fullPath, DIRECTORY_SEPARATOR));
            $current = '';
            foreach ($parts as $part) {
                $current .= DIRECTORY_SEPARATOR . $part;
                if (@is_link($current)) {
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

            if ($this->context->getScanDirectoryCursor() > 0) {
                return $this->resumeScanDirectories($options);
            }

            return $this->firstRunScanDirectories($options);
        }

        /**
         * First run: use RecursiveIteratorIterator for the initial full scan.
         */
        protected function firstRunScanDirectories($options)
        {
            try {
                $dirIterator = new RecursiveDirectoryIterator(
                    $this->context->getBaseDirectory(),
                    RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                );
                $filterIterator = new ReadableRecursiveFilterIterator(
                    $dirIterator,
                    [],
                    $this->context->getBaseDirectory(),
                    $this->context->getDirectoriesExcluded()
                );
                $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);
                $iterator->setMaxDepth(40);
            } catch (Exception $e) {
                throw new UmbrellaException('Could not open directory: ' . $this->context->getBaseDirectory(), 'directory_open_failed');
            }

            global $startTimer, $totalFilesSent, $safeTimeLimit;

            $lineNumber = 0;

            $this->siteChecksumDirectoryGenerator->startDirectory();

            if ($options['write_checksum_integrity']) {
                $this->writeDirectoriesForChecksumIntegrity($this->context->getBaseDirectory(), $options);
            } elseif ($options['write_size']) {
                $this->writeDirectoriesForSize($this->context->getBaseDirectory(), $options);
            } else {
                $this->siteChecksumDirectoryGenerator->addDirectory($this->context->getBaseDirectory());
            }

            foreach ($iterator as $fileInfo) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                    break;
                }

                try {
                    if (!$this->isDir($fileInfo)) {
                        continue;
                    }

                    $filePath = $fileInfo->getPathname();
                    $relativePath = str_replace($this->context->getBaseDirectory(), '', $filePath);

                    $lineNumber++;

                    if ($lineNumber % 500 === 0 && function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                    $this->socket->sendScanDirectoryCursor($lineNumber);

                    if ($this->hasPathLoop($relativePath, $this->context->getBaseDirectory())) {
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

        /**
         * Resume scan using a manual directory stack.
         *
         * Instead of replaying the RecursiveIteratorIterator from entry 0
         * (O(total_entries) on every resume — impossible for 1M+ entries),
         * we reconstruct the scan frontier from the dictionary file:
         *
         * 1. Read the last written path from the dictionary
         * 2. Walk its ancestor chain up to root
         * 3. For each ancestor, find filesystem children NOT in the dictionary
         * 4. Process only those unscanned subtrees via manual DFS
         *
         * Memory: O(depth × max_siblings_per_level) — typically < 1000 paths.
         * Never re-iterates already-scanned subtrees.
         */
        /**
         * Check if a directory should be entered during manual stack traversal.
         * Replicates the guards from ReadableRecursiveFilterIterator:
         * - Readable check
         * - Broken symlink rejection
         * - Circular symlink detection (self-referencing or already-visited realpath)
         * - Excluded directory filtering
         *
         * @param string $fullPath Absolute path to the directory
         * @param array &$visitedRealPaths Tracks visited symlink targets to prevent loops
         * @return bool True if the directory should be entered
         */
        protected function shouldEnterDirectory($fullPath, &$visitedRealPaths)
        {
            if (!@is_readable($fullPath)) {
                return false;
            }

            if (@is_link($fullPath)) {
                // Reject broken symlinks
                if (!file_exists($fullPath)) {
                    return false;
                }

                $linkTarget = @readlink($fullPath);
                // Reject self-referencing symlinks (. or ..)
                if ($linkTarget === '.' || $linkTarget === '..') {
                    return false;
                }

                // Reject circular symlinks (realpath already visited)
                $realPath = @realpath($fullPath);
                if ($realPath && in_array($realPath, $visitedRealPaths)) {
                    return false;
                }

                if ($realPath) {
                    $visitedRealPaths[] = $realPath;
                }
            }

            // Reject excluded directories
            $relativePath = str_replace($this->context->getBaseDirectory(), '', $fullPath);
            $excludedDirectories = $this->context->getDirectoriesExcluded();
            if (UmbrellaDirectoryExclusion::isExcluded($relativePath, $excludedDirectories)) {
                return false;
            }

            return true;
        }

        protected function resumeScanDirectories($options)
        {
            global $startTimer, $safeTimeLimit;

            $dictPath = $this->context->getDirectoryDictionaryPath();
            $baseDir = rtrim($this->context->getBaseDirectory(), DIRECTORY_SEPARATOR);
            $lineNumber = $this->context->getScanDirectoryCursor();

            // Flush and close the dictionary writer before reading to avoid partial last line
            $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();

            // Single-pass dictionary read: find last path + collect all parent→child relationships
            // This halves the I/O vs reading the file twice (Steps 1+3 merged).
            // Memory trade-off: stores all parent→child mappings (~2.5MB for 50K dirs).
            $lastRelPath = '';
            $allChildrenPerParent = [];
            $handle = @fopen($dictPath, 'r');
            if (!$handle) {
                $this->socket->sendLog('[resumeScanDirectories] Cannot open dictionary, falling back to first run');
                return $this->firstRunScanDirectories($options);
            }

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line) || strpos($line, '<?php') !== false || strpos($line, '?>') !== false) {
                    continue;
                }
                $semicolonPos = strpos($line, ';');
                if ($semicolonPos !== false) {
                    $line = substr($line, 0, $semicolonPos);
                }
                $lastRelPath = $line;
                $parentRel = dirname($line);
                $allChildrenPerParent[$parentRel][basename($line)] = true;
            }
            fclose($handle);

            if (empty($lastRelPath)) {
                $this->socket->sendLog('[resumeScanDirectories] Empty dictionary, falling back to first run');
                return $this->firstRunScanDirectories($options);
            }

            // Build ancestor chain (deepest → root)
            $ancestors = [];
            $current = $lastRelPath;
            while ($current !== '/' && $current !== '' && $current !== '.') {
                $ancestors[] = $current;
                $parent = dirname($current);
                if ($parent === $current) {
                    break;
                }
                $current = $parent;
            }
            if (empty($ancestors) || end($ancestors) !== '/') {
                $ancestors[] = '/';
            }
            $ancestorSet = array_flip($ancestors);

            // Filter to only ancestor parents (drop the rest to free memory)
            $scannedChildrenPerAncestor = array_intersect_key($allChildrenPerParent, $ancestorSet);
            unset($allChildrenPerParent);

            $this->socket->sendLog('[resumeScanDirectories] Last path: ' . $lastRelPath . ', ancestor depth: ' . count($ancestors));

            // Step 4: Build work stack — from root to deepest ancestor
            // For each ancestor, find filesystem children NOT in the dictionary.
            // Push root's children first (stack = LIFO → deepest processed first).
            $workStack = [];
            $visitedRealPaths = [];
            $reversedAncestors = array_reverse($ancestors);

            foreach ($reversedAncestors as $ancestorRelPath) {
                $ancestorFullPath = $baseDir . ($ancestorRelPath === '/' ? '' : $ancestorRelPath);

                if (!@is_dir($ancestorFullPath) || !@is_readable($ancestorFullPath)) {
                    continue;
                }

                $scannedChildren = $scannedChildrenPerAncestor[$ancestorRelPath] ?? [];

                $dirHandle = @opendir($ancestorFullPath);
                if (!$dirHandle) {
                    continue;
                }

                $unscannedChildren = [];
                while (($entry = readdir($dirHandle)) !== false) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $childFullPath = $ancestorFullPath . DIRECTORY_SEPARATOR . $entry;
                    if (!@is_dir($childFullPath) || isset($scannedChildren[$entry])) {
                        continue;
                    }
                    if (!$this->shouldEnterDirectory($childFullPath, $visitedRealPaths)) {
                        continue;
                    }
                    $unscannedChildren[] = $childFullPath;
                }
                closedir($dirHandle);

                // Push in reverse sorted order for deterministic DFS
                sort($unscannedChildren);
                foreach (array_reverse($unscannedChildren) as $child) {
                    $workStack[] = $child;
                }
            }

            $this->socket->sendLog('[resumeScanDirectories] ' . count($workStack) . ' unscanned directories to explore');

            // Reopen dictionary writer for appending new entries
            $this->siteChecksumDirectoryGenerator->openSiteChecksumDirectoryHandler();

            // Step 5: Process work stack depth-first
            while (!empty($workStack)) {
                $currentTime = time();
                if (($currentTime - $startTimer) >= $safeTimeLimit) {
                    $this->siteChecksumDirectoryGenerator->closeSiteChecksumDirectoryHandler();
                    throw new UmbrellaPreventMaxExecutionTime($lineNumber);
                }

                $currentDir = array_pop($workStack);
                $relativePath = str_replace($baseDir, '', $currentDir);

                // Depth limit (max 40 levels)
                $depth = substr_count(trim($relativePath, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) + 1;
                if ($depth > 40) {
                    continue;
                }

                if ($this->hasPathLoop($relativePath, $baseDir)) {
                    continue;
                }

                // shouldEnterDirectory already checked: readable, symlinks, excluded dirs.
                // Only staging check remains (pure regex, no I/O).
                if ($this->isStagingDirectory(basename($currentDir))) {
                    continue;
                }

                $lineNumber++;
                $this->socket->sendScanDirectoryCursor($lineNumber);

                if ($lineNumber % 500 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                if ($options['write_checksum_integrity']) {
                    $this->writeDirectoriesForChecksumIntegrity($currentDir, $options);
                } elseif ($options['write_size']) {
                    $this->writeDirectoriesForSize($currentDir, $options);
                } else {
                    $this->siteChecksumDirectoryGenerator->addDirectory($currentDir);
                }

                // Explore children — add subdirectories to stack for DFS
                $dirHandle = @opendir($currentDir);
                if (!$dirHandle) {
                    continue;
                }

                $children = [];
                while (($entry = readdir($dirHandle)) !== false) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $childPath = $currentDir . DIRECTORY_SEPARATOR . $entry;
                    if (!@is_dir($childPath)) {
                        continue;
                    }
                    if (!$this->shouldEnterDirectory($childPath, $visitedRealPaths)) {
                        continue;
                    }
                    $children[] = $childPath;
                }
                closedir($dirHandle);

                sort($children);
                foreach (array_reverse($children) as $child) {
                    $workStack[] = $child;
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
