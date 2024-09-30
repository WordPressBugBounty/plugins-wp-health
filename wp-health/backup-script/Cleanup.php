<?php

if (!class_exists('UmbrellaCleanup', false)):
    class UmbrellaCleanup
    {
        protected $context;

        public function __construct($params)
        {
            $this->context = $params['context'];
        }

        public function handleDatabase()
        {
            $this->removeDirectory($this->context->getRootDatabaseBackupDirectory());
        }

        public function handleRestore()
        {
            $this->removeDirectory($this->context->getRootRestoreDirectory());
        }

        public function handleEndProcess()
        {
            $abspath = $this->context->getBaseDirectory();

            $filePath = $abspath . DIRECTORY_SEPARATOR . 'cloner.php';

            if(file_exists($filePath)) {
                @unlink($filePath);
            }

            if(file_exists($this->context->getDictionaryPath())) {
                @unlink($this->context->getDictionaryPath());
            }

            if(file_exists($this->context->getDirectoryDictionaryPath())) {
                @unlink($this->context->getDirectoryDictionaryPath());
            }
        }

        protected function removeDirectory($path)
        {
            if (!file_exists($path)) {
                return;
            }

            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    $this->removeDirectory($filePath);
                } else {
                    @unlink($filePath);
                }
            }

            @rmdir($path);
        }
    }
endif;
