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

		protected function canProcessDirectory($directory)
		{
			if (!file_exists($directory)) {
				return false;
			}

			$directoriesExcluded = $this->getContext()->getDirectoriesExcluded();
			$dirnameForFilepath = trim(str_replace($this->getContext()->getBaseDirectory(), '', $directory));

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
			if (!file_exists($filePath)) {
				return false;
			}

			// filepath contain dictionary.php ; we sent this manually
			if (strpos($filePath, 'dictionary.php') !== false) {
				return false;
			}

			if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), $this->getContext()->getExtensionExcluded())) {
				return false;
			}

			if (@filesize($filePath) >= $this->getContext()->getFileSizeLimit()) {
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

			// If the file is older than the incremental date, we skip it
			if ($incrementalDate !== null && @filemtime($filePath) < $incrementalDate) {
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
