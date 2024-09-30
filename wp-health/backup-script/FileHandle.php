<?php

if(!class_exists('UmbrellaFileHandle', false)):
    class UmbrellaFileHandle
    {
        protected $path;
        protected $fp;
        protected $error = null;

        /**
         * @param string $path
         * @param string $mode
         * @throws UmbrellaException
         */
        public function __construct($path, $mode)
        {
            $this->path = $path;
            $this->fp = @fopen($this->path, $mode);
            if ($this->fp === false) {
                $this->error = error_get_last();
            }
        }

        public function isInError()
        {
            return $this->error !== null;
        }

        /**
         * @throws UmbrellaException
         */
        public function write($data)
        {
            if($this->fp === false) {
                return;
            }

            if ($this->fp === null) {
                throw new UmbrellaException(sprintf('File %s already closed', $this->path));
            }
            if (@fwrite($this->fp, $data) === false) {
                throw new UmbrellaException('fwrite', $this->path);
            }
        }

        /**
         * @throws UmbrellaException
         */
        public function close()
        {
            if ($this->fp === null || $this->fp === false) {
                return;
            }
            if (@fclose($this->fp) === false) {
                throw new UmbrellaException('fclose', $this->path);
            }
            $this->fp = null;
        }
    }
endif;
