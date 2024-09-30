<?php

if (!class_exists('UmbrellaSocketException', false)):
    class UmbrellaSocketException extends UmbrellaDefaultException
    {
    
        public function getErrorStrWithCode()
        {
            switch($this->errorCode) {
                case 61:
                    return 'connection_refused';
                default:
                    if(is_string($this->errorCode)) {
                        return $this->errorCode;
                    }
                    return 'unexpected_error';
            }
        }
    }
endif;
