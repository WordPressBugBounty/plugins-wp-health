<?php

if (!class_exists('UmbrellaPreventMaxExecutionTime', false)):
    class UmbrellaPreventMaxExecutionTime extends Exception
    {
        protected $cursor;

        public function __construct($cursor = 0)
        {
            $this->cursor = $cursor;
            parent::__construct('Prevent max execution time');
        }

        public function getCursor()
        {
            return $this->cursor;
        }
    }
endif;
