<?php

if (!class_exists('UmbrellaException', false)):
    class UmbrellaException extends UmbrellaDefaultException
    {
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
