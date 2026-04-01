<?php

if (!class_exists('ReadableRecursiveFilterIterator', false)) {
	class ReadableRecursiveFilterIterator extends RecursiveFilterIterator
	{
		protected $visitedPaths = [];
		protected $baseDirectory = '';
		protected $excludedDirectories = [];

		public function __construct(RecursiveIterator $iterator, array $visitedPaths = [], $baseDirectory = '', array $excludedDirectories = [])
		{
			parent::__construct($iterator);
			$this->visitedPaths = $visitedPaths;
			$this->baseDirectory = $baseDirectory;
			$this->excludedDirectories = $excludedDirectories;
		}

		#[\ReturnTypeWillChange]
		public function accept()
		{
			try {
				$current = $this->current();
				if (!$current->isReadable()){
				     return false;
				}

				if ($current->isLink() && !file_exists($current->getPathname())) {
					return false;
				}
			} catch (Exception $e) {
				return false;
			}
			return true;
		}

		/**
		 * Check if a directory is excluded based on the excluded directories list.
		 * This prevents the iterator from ever descending into excluded directories,
		 * avoiding errors with volatile directories (e.g. cache plugins that delete
		 * directories during iteration).
		 */
		protected function isExcludedDirectory($pathname)
		{
			if (empty($this->excludedDirectories) || empty($this->baseDirectory)) {
				return false;
			}

			$relativePath = str_replace($this->baseDirectory, '', $pathname);
			if (empty($relativePath)) {
				return false;
			}

			return UmbrellaDirectoryExclusion::isExcluded($relativePath, $this->excludedDirectories);
		}

		public function hasChildren(): bool
		{
			$current = $this->current();

			if (!$current->isDir()) {
				return false;
			}

			if ($this->isExcludedDirectory($current->getPathname())) {
				return false;
			}

			if ($current->isLink() && (
					$current->getLinkTarget() === '.' ||
					$current->getLinkTarget() === '..' ||
					strpos($current->getRealPath(), $current->getLinkTarget()) !== false
				)) {
				return false;
			}

			if (!$current->isLink() || empty($this->visitedPaths)) {
				return $this->getInnerIterator()->hasChildren();
			}

			$realPath = $current->getRealPath();

			if (!in_array($realPath, $this->visitedPaths)) {
				return $this->getInnerIterator()->hasChildren();
			}

			return false;
		}

		#[\ReturnTypeWillChange]
		public function getChildren()
		{
			$current = $this->current();
			$nextPaths = $this->visitedPaths;

			if ($current->isLink()) {
				$nextPaths[] = $current->getRealPath();
			}

			return new self($this->getInnerIterator()->getChildren(), $nextPaths, $this->baseDirectory, $this->excludedDirectories);
		}
	}
}
