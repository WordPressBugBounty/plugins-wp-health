<?php

if (!class_exists('UmbrellaDefaultException', false)):
    class UmbrellaDefaultException extends Exception
    {
        protected $error = '';
        protected $errorCode = '';
        protected $internalError = '';

        const ERROR_UNEXPECTED = 'error_unexpected';

        /**
         * @param string $error
         * @param string $code
         */
        public function __construct($error, $code = self::ERROR_UNEXPECTED, $internalError = '')
        {
            $this->message = sprintf('[%s]: %s', $code, $error);
            $this->error = $error;
            $this->errorCode = (string)$code;
            $this->internalError = $internalError;
        }

        public function getError()
        {
            return $this->error;
        }

        public function getErrorCode()
        {
            return $this->errorCode;
        }

        public function getInternalError()
        {
            return $this->internalError;
        }

        public function getErrorMessage()
        {
            return $this->message;
        }

        public function getErrorStrWithCode()
        {
            switch($this->errorCode) {
                default:
                    if(is_string($this->errorCode)) {
                        return $this->errorCode;
                    }
                    return 'unexpected_error';
            }
        }
    }
endif;
