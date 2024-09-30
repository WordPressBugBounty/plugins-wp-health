<?php

if (!class_exists('UmbrellaDatabasePreventMaxExecutionTime', false)):
    class UmbrellaDatabasePreventMaxExecutionTime extends Exception
    {
        protected $cursor;

        public function __construct($cursor)
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
