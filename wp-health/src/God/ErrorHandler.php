<?php
namespace WPUmbrella\God;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Helpers\GodTransient;

require_once __DIR__ . '/ErrorBuffer.php';

class ErrorHandler
{
    const DEDUPLICATION_WINDOW = 43200;

    const DEDUPLICATION_MAX_ENTRIES = 1000;

    const SUPPRESSED_MASK = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;

    const BACKTRACE_DEPTH = 20;

    const BACKTRACE_MAX_BYTES = 4000;

    const BACKTRACE_TRUNCATION_MARKER = '... [truncated]';

    protected static $rootPath = null;

    protected $isFlushing = false;

    protected $pendingErrors = [];

    public function init()
    {
        set_error_handler([$this, 'handler']);
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    public function handler($code, $message, $file, $line, $ctx = [])
    {
        if ($this->isFlushing) {
            return false;
        }

        if (error_reporting() === self::SUPPRESSED_MASK) {
            return false;
        }

        $params = [
            'message' => $message,
            'file' => $file,
            'code' => $code,
            'line' => $line,
        ];

        $key = $this->serializeError($params);

        // The same error can fire thousands of times in a single request.
        // Capturing the backtrace once per distinct error keeps debug_backtrace
        // out of the hot loop.
        if (isset($this->pendingErrors[$key])) {
            return;
        }

        $params['date_error'] = gmdate('Y-m-d\TH:i:s\Z');
        $params['backtrace'] = $this->captureBacktrace();

        $this->pendingErrors[$key] = $params;
    }

    /**
     * Returns the current call stack as one frame per line.
     *
     * DEBUG_BACKTRACE_IGNORE_ARGS keeps the argument values out of the frames.
     * Only file, line, class and function are kept, paths are made relative to
     * the WordPress root, and the result is capped in bytes.
     *
     * @return string
     */
    public function captureBacktrace()
    {
        return $this->serializeBacktrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::BACKTRACE_DEPTH));
    }

    /**
     * @param array $frames
     *
     * @return string
     */
    public function serializeBacktrace(array $frames)
    {
        $root = self::rootPath();
        $lines = [];

        foreach ($frames as $frame) {
            $class = isset($frame['class']) ? (string) $frame['class'] : '';

            if (__CLASS__ === $class) {
                continue;
            }

            $function = isset($frame['function']) ? (string) $frame['function'] : '';
            $file = isset($frame['file']) ? (string) $frame['file'] : '';
            $line = isset($frame['line']) ? (string) $frame['line'] : '';

            if ('' !== $file && '' !== $root) {
                $file = str_replace($root, '', $file);
            }

            $location = '' === $file ? '[internal]' : $file . ':' . $line;
            $call = '' === $class ? $function : $class . '::' . $function;

            $lines[] = trim($location . ' ' . $call);
        }

        $serialized = implode("\n", $lines);

        if (strlen($serialized) <= self::BACKTRACE_MAX_BYTES) {
            return $serialized;
        }

        $keep = self::BACKTRACE_MAX_BYTES - strlen(self::BACKTRACE_TRUNCATION_MARKER);

        return substr($serialized, 0, $keep) . self::BACKTRACE_TRUNCATION_MARKER;
    }

    /**
     * @return string
     */
    protected static function rootPath()
    {
        if (null === self::$rootPath) {
            $resolved = realpath(ABSPATH);
            self::$rootPath = is_string($resolved) ? $resolved : '';
        }

        return self::$rootPath;
    }

    public function shutdownHandler()
    {
        $lastError = error_get_last();
        if (null !== $lastError) {
            $this->handler($lastError['type'], $lastError['message'], $lastError['file'], $lastError['line']);
        }

        $this->flush();
    }

    public function flush()
    {
        if (empty($this->pendingErrors)) {
            return;
        }

        $pendingErrors = $this->pendingErrors;
        $this->pendingErrors = [];
        $this->isFlushing = true;

        if (get_option('wp_health_allow_tracking')) {
            foreach ($this->rejectAlreadySent($pendingErrors) as $params) {
                $this->saveError($params);
            }
        }

        $this->isFlushing = false;
    }

    /**
     * Returns the errors of the batch that are not muted yet, and marks them as
     * muted. The map is read once and written once for the whole batch, and the
     * write is skipped when the batch left it unchanged.
     *
     * @param array $pendingErrors
     *
     * @return array
     */
    protected function rejectAlreadySent(array $pendingErrors)
    {
        $now = time();

        $transient = get_transient(GodTransient::ERROR_ALREADY_SEND);
        if (!is_array($transient)) {
            $transient = [];
        }

        $entryCount = count($transient);

        // Each entry carries its own expiry. The transient lifetime is refreshed on every
        // write, so relying on it alone would mute a recurring error forever on a site that
        // keeps producing new ones.
        $transient = array_filter($transient, function ($expiresAt) use ($now) {
            return is_int($expiresAt) && $expiresAt > $now;
        });

        $changed = count($transient) !== $entryCount;
        $fresh = [];

        foreach ($pendingErrors as $params) {
            $md5 = $this->serializeError($params);

            if (isset($transient[$md5]) && $transient[$md5] > $now) {
                continue;
            }

            $transient[$md5] = $now + self::DEDUPLICATION_WINDOW;
            $changed = true;
            $fresh[] = $params;
        }

        $overflow = count($transient) - self::DEDUPLICATION_MAX_ENTRIES;

        if ($overflow > 0) {
            $transient = array_slice($transient, $overflow, null, true);
            $changed = true;
        }

        if ($changed) {
            set_transient(GodTransient::ERROR_ALREADY_SEND, $transient, self::DEDUPLICATION_WINDOW);
        }

        return $fresh;
    }

    public function errorAlreadyExist($params)
    {
        return 0 === count($this->rejectAlreadySent([$params]));
    }

    public function saveError($params)
    {
        ErrorBuffer::add($this->serializeError($params), $params);
    }

    public function serializeError($params)
    {
        return md5(vsprintf('%s-%s-%s-%s', [$params['file'], $params['line'], $params['code'], $params['message']]));
    }
}
