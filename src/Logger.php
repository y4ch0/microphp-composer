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
            @mkdir($dir, 0755, true);
        }
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            '[%s] %s: %s %s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
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
