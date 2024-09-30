<?php

if (!class_exists('ReadableRecursiveFilterIterator', false)) {
    class ReadableRecursiveFilterIterator extends RecursiveFilterIterator
    {
        #[\ReturnTypeWillChange]
        public function accept()
        {
            try {
                return $this->current()->isReadable();
            } catch (Exception $e) {
                return false;
            }
        }
    }
}
