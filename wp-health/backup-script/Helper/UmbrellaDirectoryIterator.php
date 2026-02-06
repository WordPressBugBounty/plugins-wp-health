<?php

if (!class_exists('ReadableRecursiveFilterIterator', false)) {
	class ReadableRecursiveFilterIterator extends RecursiveFilterIterator
	{
		protected $visitedPaths = [];

		public function __construct(RecursiveIterator $iterator, array $visitedPaths = [])
		{
			parent::__construct($iterator);
			$this->visitedPaths = $visitedPaths;
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

		public function hasChildren(): bool
		{
			$current = $this->current();

			if (!$current->isDir()) {
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

			return new self($this->getInnerIterator()->getChildren(), $nextPaths);
		}
	}
}
