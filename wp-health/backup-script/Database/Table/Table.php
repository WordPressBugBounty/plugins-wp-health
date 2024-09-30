<?php
if (!class_exists('UmbrellaTable', false)):
    class UmbrellaTable
    {
        public $name = '';
        public $type = 0;
        public $size = 0;
        public $dataSize = 0;
        public $storage = '';
        public $done = false;
        public $listed = false;
        /** @var UmbrellaColumn[] */
        public $columns = array();
        public $path = '';
        public $noData = false;
        public $hash = '';
        public $source = '';

        public static function fromArray(array $data)
        {
            $table = new self;
            if (isset($data['name'])) {
                $table->name = $data['name'];
            }
            if (isset($data['type'])) {
                $table->type = $data['type'];
            }
            if (isset($data['size'])) {
                $table->size = $data['size'];
            }
            if (isset($data['dataSize'])) {
                $table->dataSize = $data['dataSize'];
            }
            if (isset($data['storage'])) {
                $table->storage = $data['storage'];
            }
            if (isset($data['done'])) {
                $table->done = $data['done'];
            }
            if (isset($data['listed'])) {
                $table->listed = $data['listed'];
            }
            if (isset($data['columns'])) {
                foreach ($data['columns'] as $column) {
                    $table->columns[] = UmbrellaColumn::fromArray($column);
                }
            }
            if (isset($data['path'])) {
                $table->path = $data['path'];
            }
            if (isset($data['noData'])) {
                $table->noData = $data['noData'];
            }
            if (isset($data['source'])) {
                $table->source = $data['source'];
            }
            if (isset($data['hash'])) {
                $table->hash = $data['hash'];
            }
            return $table;
        }
    }
endif;
