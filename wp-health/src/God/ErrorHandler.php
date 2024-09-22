<?php
namespace WPUmbrella\God;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\GodTransient;

class ErrorHandler
{
    public function init()
    {
        set_error_handler([$this, 'handler']);
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    public function handler($code, $message, $file, $line, $ctx = [])
    {
        $allowTracking = get_option('wp_health_allow_tracking');

        if (!$allowTracking) {
            return false;
        }

        $params = [
            'message' => $message,
            'file' => $file,
            'code' => $code,
            'line' => $line,
        ];

        $alreadyExist = $this->errorAlreadyExist($params);

        if ($alreadyExist) {
            return;
        }

        // $params['backtrace'] = debug_backtrace();

        $this->saveError($params);
    }

    public function shutdownHandler()
    {
        $lastError = error_get_last();
        if (null !== $lastError) {
            $this->handler($lastError['type'], $lastError['message'], $lastError['file'], $lastError['line']);
        }
    }

    public function errorAlreadyExist($params)
    {
        $transient = get_transient(GodTransient::ERROR_ALREADY_SEND);
        $md5 = $this->serializeError($params);

        if (!$transient) {
            $transient = [];
            $transient[] = $md5;
            set_transient(GodTransient::ERROR_ALREADY_SEND, $transient, 43200);

            return false;
        }

        if (!is_array($transient)) {
            $transient = [];
        }

        if (in_array($md5, $transient)) {
            return true;
        }

        $transient[] = $md5;
        set_transient(GodTransient::ERROR_ALREADY_SEND, $transient, 43200);

        return false;
    }

    public function saveError($params)
    {
        $serialize = $this->serializeError($params);

        if (false === ($transientError = get_transient(GodTransient::ERRORS_SAVE))) {
            $transientError = [];
            $transientError[$serialize] = $params;
            set_transient(GodTransient::ERRORS_SAVE, $transientError);
        } else {
            if (!array_key_exists($serialize, $transientError)) {
                $transientError[$serialize] = $params;
                set_transient(GodTransient::ERRORS_SAVE, $transientError);
            }
        }
    }

    public function serializeError($params)
    {
        return md5(vsprintf('%s-%s-%s-%s', [$params['file'], $params['line'], $params['code'], $params['message']]));
    }
}
