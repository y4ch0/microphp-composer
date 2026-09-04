<?php
/**
 * MicroPHP Framework
 * Minimal file logger, shaped like PSR-3's level methods without pulling in
 * psr/log as a dependency. Swap this class for Monolog later by binding
 * Logger::class to a Monolog adapter in the container — nothing else in the
 * framework depends on the concrete implementation.
 */

namespace MicroPHP;

class Logger
{
    public function __construct(private string $logFile)
    {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_dir($dir) && DIRECTORY_SEPARATOR !== '\\') {
            @chmod($dir, 0750);
        }
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $line = json_encode([
            'timestamp' => date(DATE_ATOM),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (is_string($line) && @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
            @chmod($this->logFile, 0600);
        }
    }

    /** @param array<string,mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}
