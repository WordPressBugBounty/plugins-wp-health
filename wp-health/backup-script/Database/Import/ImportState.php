<?php

if (!class_exists('UmbrellaImportState', false)):
    class UmbrellaImportState
    {
        public $file;
        /** @var string Collects skipped statements up to a certain buffer length. */
        public $skip = '';
        /** @var int Counts skipped statements. */
        public $skipCount = 0;
        /** @var int Keeps skipped statements' total size. */
        public $skipSize = 0;
        /** @var UmbrellaImportDump[] File dumps that should be imported. */
        public $files = [];

        /** @var int Maximum buffer size for skipped statements. */
        private $skipBuffer = 0;

        /**
         * @param array $data       State array; empty state means there's nothing to process. Every file that should be imported
         *                          must contain the props $state['files'][$i]['path'] and $state['files'][$i]['size'].
         * @param int   $skipBuffer Maximum buffer size for skipped statement logging.
         *
         * @return UmbrellaImportState
         */
        public static function fromArray(array $data, $skipBuffer = 0)
        {
            $state = new self;
            $state->skipBuffer = $skipBuffer;

            foreach ((array)@$data['files'] as $i => $dump) {
                $state->files[$i] = new UmbrellaImportDump(
                    $dump['size'],
                    $dump['processed'],
                    $dump['path'],
                    $dump['encoding'],
                    $dump['source'],
                    $dump['type']
                );
            }

            $state->skip = 0; //(string)@$data['skip'];
            $state->skipCount = 0; // (int)@$data['skipCount'];
            $state->skipSize = 0; // (int)@$data['skipSize'];
            return $state;
        }

        /**
          * @return ClonerImportDump|null The next dump in the queue, or null if there are none left.
          */
        public function next()
        {
            foreach ($this->files as $file) {
                if ($file->processed < $file->size) {
                    return $file;
                }
            }
            return null;
        }

        /**
         * Pushes the first available file dump to the end of the queue.
         */
        public function pushNextToEnd()
        {
            $carry = null;
            foreach ($this->files as $i => $file) {
                if ($file->size === $file->processed) {
                    continue;
                }
                $carry = $file;
                unset($this->files[$i]);
                $this->files = array_values($this->files);
                break;
            }

            if ($carry === null) {
                return;
            }

            $this->files[] = $carry;
        }

        /**
         * Add a "skipped statement" to the state if there's any place left in state's "skipped statement" buffer.
         * Also updates state's "skipped statement" count and size.
         *
         * @param string $statements Statements that were skipped.
         */
        public function skipStatement($statements)
        {
            $length = strlen($statements);
            if (strlen($this->skip) + $length <= $this->skipBuffer / 2) {
                // Only write full statements to the buffer if it won't exceed half the buffer.
                $this->skip .= $statements;
            } elseif ($length + 200 <= $this->skipBuffer) {
                // We have enough space in the buffer to log the excerpt, but don't overflow the buffer, skip logging
                // when we reach its limit.
                $this->skip .= sprintf('/* query too big (%d bytes), excerpt: %s */;', $length, substr($statements, 0, 100));
            }

            $this->skipCount++;
            $this->skipSize += $length;
        }
    }
endif;
