<?php

if (!class_exists('UmbrellaDirectoryExclusion', false)):
	class UmbrellaDirectoryExclusion
	{
		/**
		 * Check if a path matches an excluded directory pattern.
		 *
		 * Uses strpos to find the pattern anywhere in the path (handles WP in subdirectories),
		 * then verifies the match is at a directory boundary (next char is / or end of string)
		 * to avoid false positives like /cached-assets matching /cache.
		 *
		 * @param string $path The relative path to check
		 * @param string $excludedDir The exclusion pattern (e.g. /wp-content/cache)
		 * @return bool True if the path should be excluded
		 */
		public static function matches($path, $excludedDir)
		{
			$rootOnly = false;
			if (strpos($excludedDir, '^') === 0) {
				$rootOnly = true;
				$excludedDir = substr($excludedDir, 1);
			}

			$cleanDir = DIRECTORY_SEPARATOR . trim($excludedDir, DIRECTORY_SEPARATOR);
			$cleanPath = DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);

			$pos = strpos($cleanPath, $cleanDir);
			if ($pos === false) {
				return false;
			}

			if ($rootOnly && $pos !== 0) {
				return false;
			}

			$afterMatch = $pos + strlen($cleanDir);

			return $afterMatch === strlen($cleanPath) || $cleanPath[$afterMatch] === DIRECTORY_SEPARATOR;
		}

		/**
		 * Check if a path matches any of the excluded directory patterns.
		 *
		 * @param string $path The relative path to check
		 * @param array $excludedDirectories List of exclusion patterns
		 * @return bool True if the path should be excluded
		 */
		public static function isExcluded($path, array $excludedDirectories)
		{
			foreach ($excludedDirectories as $dir) {
				if (self::matches($path, $dir)) {
					return true;
				}
			}

			return false;
		}
	}
endif;
