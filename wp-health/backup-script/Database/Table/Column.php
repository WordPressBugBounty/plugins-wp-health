<?php

if (!class_exists('UmbrellaDatabaseColumn', false)):
    class UmbrellaDatabaseColumn
    {
        public $name = '';
        public $type = '';
    
        public static function fromArray(array $data)
        {
            $column = new self;
            if (isset($data['name'])) {
                $column->name = $data['name'];
            }
            if (isset($data['type'])) {
                $column->type = $data['type'];
            }
            return $column;
        }
    }
endif;
