<?php

if (!class_exists('UmbrellaAbstractProcessBackup', false)):
    class UmbrellaAbstractProcessBackup
    {
        /**
         * @var UmbrellaContext
         */
        protected $context;

        /**
         * @var UmbrellaWebSocket
         */
        protected $socket;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;
        }
    }
endif;
