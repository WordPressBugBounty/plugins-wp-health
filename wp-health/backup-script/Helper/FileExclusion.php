<?php

if (!class_exists('UmbrellaFileExclusion', false)):
	class UmbrellaFileExclusion
	{
		/**
		 * Check if a file should be excluded from the scan.
		 *
		 * A bare pattern matches the file name anywhere in the tree, which is what
		 * this list has always meant. A pattern prefixed with ^ matches only the
		 * file sitting at the root, the same convention the directory exclusions
		 * use: a name we drop at the root must not take a customer's own file of
		 * the same name out of their backup.
		 *
		 * @param string $filePath Absolute path of the file
		 * @param string $baseDirectory Absolute path of the scan root
		 * @param array $excludedFiles List of patterns
		 * @return bool True if the file should be skipped
		 */
		public static function isExcluded($filePath, $baseDirectory, array $excludedFiles)
		{
			$basename = basename($filePath);
			$root = rtrim((string) $baseDirectory, DIRECTORY_SEPARATOR);

			foreach ($excludedFiles as $excluded) {
				if (strpos($excluded, '^') !== 0) {
					if ($excluded === $basename) {
						return true;
					}

					continue;
				}

				if ($root === '') {
					continue;
				}

				if ($filePath === $root . DIRECTORY_SEPARATOR . substr($excluded, 1)) {
					return true;
				}
			}

			return false;
		}
	}
endif;
