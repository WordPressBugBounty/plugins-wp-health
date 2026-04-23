<?php
namespace WPUmbrella\Services\Provider\SystemReport;

if (!defined('ABSPATH')) {
    exit;
}

class ErrorLogCollector implements CollectorInterface
{
    use ErrorLogPathTrait;

    /** @var int */
    protected $lines;

    public function __construct($lines = 50)
    {
        $this->lines = $lines;
    }

    public function getId()
    {
        return 'error_log';
    }

    public function collect()
    {
        $logPath = $this->resolveErrorLogPath();

        if (!$logPath || !file_exists($logPath) || !is_readable($logPath)) {
            return [
                'available' => false,
                'reason' => !$logPath ? 'no_log_path' : 'not_readable',
                'lines' => [],
            ];
        }

        $lines = $this->readLastLines($logPath, $this->lines);

        // Extract time range from log lines
        $firstTimestamp = $this->extractTimestamp($lines[0]);
        $lastTimestamp = $this->extractTimestamp($lines[count($lines) - 1]);

        // Count unique error patterns (dedup repeated errors)
        $uniqueErrors = $this->countUniqueErrors($lines);

        return [
            'available' => true,
            'line_count' => count($lines),
            'time_range' => [
                'oldest_entry' => $firstTimestamp,
                'newest_entry' => $lastTimestamp,
            ],
            'unique_error_count' => $uniqueErrors,
            'lines' => $lines,
        ];
    }

    protected function readLastLines($path, $count)
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $chunkSize = 8192;
        $maxRead = 2 * 1024 * 1024; // 2MB max
        $totalRead = 0;
        $buffer = '';

        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        while ($totalRead < $maxRead && $totalRead < $fileSize) {
            $readSize = min($chunkSize, $fileSize - $totalRead);
            $totalRead += $readSize;
            fseek($handle, -$totalRead, SEEK_END);
            $buffer = fread($handle, $readSize) . $buffer;

            $lineCount = substr_count($buffer, "\n");
            if ($lineCount >= $count + 1) {
                break;
            }

            $chunkSize *= 2;
        }

        fclose($handle);

        $lines = explode("\n", trim($buffer));
        $lines = array_slice($lines, -$count);

        return array_map([$this, 'redactSensitiveData'], $lines);
    }

    protected function extractTimestamp($line)
    {
        // Match PHP error log format: [DD-Mon-YYYY HH:MM:SS UTC]
        if (preg_match('/\[(\d{2}-\w{3}-\d{4}\s\d{2}:\d{2}:\d{2}\s\w+)\]/', $line, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function countUniqueErrors($lines)
    {
        $patterns = [];

        foreach ($lines as $line) {
            // Extract the error message without timestamp and file path details
            if (preg_match('/(?:Fatal error|Warning|Notice|Deprecated|Parse error):\s*(.+?)(?:\s+in\s+\/|$)/i', $line, $matches)) {
                $key = substr(trim($matches[1]), 0, 100);
                $patterns[$key] = true;
            }
        }

        return count($patterns);
    }

    protected function redactSensitiveData($line)
    {
        // Redact key=value patterns for sensitive keys
        $line = preg_replace(
            '/\b(password|passwd|secret|token|api_key|apikey|api[-_]?secret|access[-_]?key|private[-_]?key)\s*[=:]\s*\S+/i',
            '$1=[REDACTED]',
            $line
        );

        // Redact Authorization headers
        $line = preg_replace(
            '/Authorization:\s*\S+(\s+\S+)?/i',
            'Authorization: [REDACTED]',
            $line
        );

        // Redact database connection strings
        $line = preg_replace(
            '/(mysql|pgsql|mysqli|postgres|mariadb):\/\/[^\s]+/i',
            '$1://[REDACTED]',
            $line
        );

        return $line;
    }
}
