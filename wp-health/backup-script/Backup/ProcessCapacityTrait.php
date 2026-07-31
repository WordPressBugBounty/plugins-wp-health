<?php
if (!trait_exists('UmbrellaProcessCapacityTrait', false)):
	trait UmbrellaProcessCapacityTrait {

		/**
		 * @param string $filePath
		 *
		 * @return bool
		 */
		protected function checkProcessFile($filePath) {
			if (!$this->canProcessFile($filePath)) {
				return false;
			}

			return true;
		}

		protected function isStagingDirectory($directoryName)
		{
			$patterns = [
				'/^[a-z0-9]+-staging\.wpdns\.site$/i',       // Kinsta
				'/^[a-z0-9]+-staging\.onrocket\.site$/i',    // Starter/OnRocket
				'/^[a-z0-9]+-staging\.kinsta\.cloud$/i',     // Kinsta Cloud
				'/^[a-z0-9]+-staging\.temp-dns\.com$/i',     // Starter alt
			];

			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $directoryName)) {
					return true;
				}
			}
			return false;
		}

		protected function canProcessDirectory($directory)
		{
			if (!file_exists($directory)) {
				return false;
			}

			if ($this->isStagingDirectory(basename($directory))) {
				return false;
			}

			$directoriesExcluded = $this->getContext()->getDirectoriesExcluded();
			$dirnameForFilepath = trim(str_replace($this->getContext()->getBaseDirectory(), '', $directory));

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

			// Skip directory-marker entries. A plugin/theme uploaded from a Windows zip
			// extracted on Linux leaves flat files whose name ends with a path separator
			// (e.g. "gebp-...\lib\composer\installers\.github\"). When sending, the path
			// is normalized backslash->slash (see UmbrellaWebSocket::send), turning these
			// into a path ending in "/", which the mirror cannot open/write (ENOENT) and
			// which aborts the whole file transfer. They carry no content (empty dir
			// placeholders), so skip them.
			$normalizedPath = str_replace('\\', DIRECTORY_SEPARATOR, $filePath);
			if (substr($normalizedPath, -1) === DIRECTORY_SEPARATOR) {
				return false;
			}

			if (preg_match('/^c[a-z0-9]{15,}-((directory|directories-checksum)-)?dictionary\.php$/', basename($filePath))) {
				return false;
			}

			if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), $this->getContext()->getExtensionExcluded())) {
				return false;
			}

			$context = $this->getContext();
			$cap = method_exists($context, 'getFileSizeLimitForPath')
				? $context->getFileSizeLimitForPath($filePath)
				: $context->getFileSizeLimit();
			if (@filesize($filePath) >= $cap) {
				return false;
			}

			if (in_array(basename($filePath), $this->getContext()->getFilesExcluded())) {
				return false;
			}

			return true;
		}

		public function canProcessIncrementalFile($filePath)
		{
			$incrementalDate = $this->getContext()->getIncrementalDate();

			// Use the most recent of mtime and ctime. Plugin/theme updates and
			// Composer/rsync deploys preserve the archive's mtime (often backdated
			// to the release date), so mtime alone misses freshly-deployed files.
			// ctime is set by the OS when the inode is written and cannot be
			// backdated by extraction tooling.
			$lastChange = max(@filemtime($filePath) ?: 0, @filectime($filePath) ?: 0);

			// If the file is older than the incremental date, we skip it
			if ($incrementalDate !== null && $lastChange < $incrementalDate) {
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
		 * @return UmbrellaContext
		 */
		abstract protected function getContext();
	}
endif;
