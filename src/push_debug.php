<?php
/**
 * Push Debug Logger
 * Writes detailed step-by-step logs to a dedicated file for diagnosing
 * why automatic push notifications may not be arriving.
 *
 * Log file: storage/logs/push_debug.log
 */
declare(strict_types=1);

function pushDebugLog(string $message, array $context = []): void
{
    static $logDir = null;
    if ($logDir === null) {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    $ts = date('Y-m-d H:i:s');
    $requestId = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);

    // Add caller info
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $caller = '';
    if (isset($bt[1])) {
        $file = basename($bt[1]['file'] ?? '?');
        $line = $bt[1]['line'] ?? '?';
        $func = $bt[1]['function'] ?? '?';
        $caller = "{$file}:{$line} ({$func})";
    }

    $line = "[{$ts}] [{$requestId}] [{$caller}] {$message}";
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= "\n";

    $logFile = $logDir . '/push_debug.log';
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    // Also send to error_log for redundancy
    error_log('[PushDebug] ' . $message . (!empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''));
}

/**
 * Read the last N lines of the push debug log.
 */
function pushDebugReadLog(int $lines = 100): string
{
    $logFile = __DIR__ . '/../storage/logs/push_debug.log';
    if (!file_exists($logFile)) {
        return '(log vazio — nenhum evento registrado ainda)';
    }

    $content = file_get_contents($logFile);
    if ($content === false) return '(erro ao ler log)';

    $allLines = explode("\n", trim($content));
    $lastLines = array_slice($allLines, -$lines);
    return implode("\n", $lastLines);
}

/**
 * Clear the push debug log.
 */
function pushDebugClearLog(): void
{
    $logFile = __DIR__ . '/../storage/logs/push_debug.log';
    @file_put_contents($logFile, '');
}
