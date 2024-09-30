<?php

if (!class_exists('UmbrellaImportDump', false)):
    class UmbrellaImportDump
    {
        public $size = 0;
        public $processed = 0;
        public $path = '';
        public $encoding = '';
        public $source = '';
        public $type = 0;

        public function __construct($size, $processed, $path, $encoding, $source, $type)
        {
            $this->size = (int)$size;
            $this->processed = (int)$processed;
            $this->path = (string)$path;
            $this->encoding = (string)$encoding;
            $this->source = (string)$source;
            $this->type = (int)$type;
        }
    }
endif;
