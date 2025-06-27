<?php

if (!class_exists('UmbrellaErrorHandler', false)):
    class UmbrellaErrorHandler
    {
        const MAX_SIZE_LOG_FILE = 5242880; // 5 Mo = 5 * 1024 * 1024 octets

        private $logFile;
        private $reservedMemory;
        private static $lastError;
        private $requestID;
        private $socket;

        public function __construct($logFile)
        {
            $this->logFile = $logFile;
        }

        public function register()
        {
            $this->reservedMemory = str_repeat('x', 10240);
            register_shutdown_function([$this, 'handleFatalError']);
            set_error_handler([$this, 'handleError']);
            set_exception_handler([$this, 'handleException']);
        }

        public function unregister()
        {
            if (file_exists($this->logFile)) {
                @unlink($this->logFile);
            }
        }

        public function setSocket($socket)
        {
            $this->socket = $socket;
        }

        /**
         * @return array
         */
        public static function lastError()
        {
            return self::$lastError;
        }

        public function handleError($type, $message, $file, $line)
        {
            self::$lastError = compact('message', 'type', 'file', 'line');
            if (error_reporting() === 0) {
                // Muted error.
                return;
            }
            if (!strlen($message)) {
                $message = 'empty error message';
            }
            $args = func_get_args();
            if (count($args) >= 6 && $args[5] !== null && $type & E_ERROR) {
                // 6th argument is backtrace.
                // E_ERROR fatal errors are triggered on HHVM when
                // hhvm.error_handling.call_user_handler_on_fatals=1
                // which is the way to get their backtrace.
                $this->handleFatalError(compact('type', 'message', 'file', 'line'));

                return;
            }
            list($file, $line) = self::getFileLine($file, $line);
            $this->log(sprintf('%s: %s in %s on line %d', self::codeToString($type), $message, $file, $line));
        }

        private static function getFileLine($file, $line)
        {
            if (__FILE__ !== $file) {
                return [$file, $line];
            }
            if (function_exists('__bundler_sourcemap')) {
                $globalOffset = 0;
                foreach (__bundler_sourcemap() as $offsetPath) {
                    list($offset, $path) = $offsetPath;
                    if ($line <= $offset) {
                        return [$path, $line - $globalOffset + 1];
                    }
                    $globalOffset = $offset;
                }
            }
            return [$file, $line];
        }

        /**
         * @param Exception|Error $e
         */
        public function handleException($e)
        {
            list($file, $line) = self::getFileLine($e->getFile(), $e->getLine());
            $this->log(sprintf('Unhandled exception in file %s line %d: %s', $file, $line, $e->getMessage()));
            exit;
        }

        public function handleFatalError(?array $error = null)
        {
            $this->reservedMemory = null;
            if ($error === null) {
                // Since default PHP implementation doesn't call error handlers on fatal errors, the self::$lastError
                // variable won't be updated. That's why this is the only place where we call error_get_last() directly.
                $error = error_get_last();
            }
            if (!$error) {
                return;
            }
            if (!in_array($error['type'], [E_PARSE, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
                return;
            }
            list($file, $line) = self::getFileLine($error['file'], $error['line']);
            $message = sprintf('%s: %s in %s on line %d', self::codeToString($error['type']), $error['message'], $file, $line);
            $this->log($message);
            exit;
        }

        private function log($message)
        {
            if (file_exists($this->logFile) && filesize($this->logFile) >= self::MAX_SIZE_LOG_FILE) {
                return;
            }

            if (($fp = fopen($this->logFile, 'a')) === false) {
                return;
            }
            if (flock($fp, LOCK_EX) === false) {
                fclose($fp);
                return;
            }

            $value = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);

            if ($this->socket !== null) {
                $this->socket->sendLog($value, true);
            }

            if (fwrite($fp, $value) === false) {
                fclose($fp);
                return;
            }
            fclose($fp);
        }

        private static function codeToString($code)
        {
            switch ($code) {
                case E_ERROR:
                    return 'E_ERROR';
                case E_WARNING:
                    return 'E_WARNING';
                case E_PARSE:
                    return 'E_PARSE';
                case E_NOTICE:
                    return 'E_NOTICE';
                case E_CORE_ERROR:
                    return 'E_CORE_ERROR';
                case E_CORE_WARNING:
                    return 'E_CORE_WARNING';
                case E_COMPILE_ERROR:
                    return 'E_COMPILE_ERROR';
                case E_COMPILE_WARNING:
                    return 'E_COMPILE_WARNING';
                case E_USER_ERROR:
                    return 'E_USER_ERROR';
                case E_USER_WARNING:
                    return 'E_USER_WARNING';
                case E_USER_NOTICE:
                    return 'E_USER_NOTICE';
                case E_STRICT:
                    return 'E_STRICT';
                case E_RECOVERABLE_ERROR:
                    return 'E_RECOVERABLE_ERROR';
                case E_DEPRECATED:
                    return 'E_DEPRECATED';
                case E_USER_DEPRECATED:
                    return 'E_USER_DEPRECATED';
            }
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50300) {
                switch ($code) {
                    case E_DEPRECATED:
                        return 'E_DEPRECATED';
                    case E_USER_DEPRECATED:
                        return 'E_USER_DEPRECATED';
                }
            }
            return 'E_UNKNOWN';
        }
    }
endif;
