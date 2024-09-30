<?php

if (!class_exists('UmbrellaAbstractProcessBackup', false)):
    class UmbrellaAbstractProcessBackup
    {
        protected $context;

        protected $socket;

        protected $directoryDictionaryHandle;

        public function __construct($params)
        {
            $this->context = $params['context'] ?? null;
            $this->socket = $params['socket'] ?? null;
            $this->directoryDictionaryHandle = fopen($this->context->getDirectoryDictionaryPath(), 'r');
        }

        /**
        * Close the files dictionary
        */
        public function closeDictionaries()
        {
            if (is_resource($this->directoryDictionaryHandle)) {
                fclose($this->directoryDictionaryHandle);
            }
        }
    }
endif;
