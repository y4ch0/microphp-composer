<?php
/**
 * MicroPHP Framework
 * Centralized exception/error handling. Replaces scattering
 * ini_set('display_errors', 1) at the entry point (which leaked stack
 * traces in production regardless of environment) with a single place
 * that logs everything and only *displays* details when APP_DEBUG is on.
 */

namespace MicroPHP;

use ErrorException;
use Throwable;

class ExceptionHandler
{
    public function __construct(
        private Logger $logger,
        private bool $debug,
    ) {
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
    }

    public function handleException(Throwable $e): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($this->debug) {
            echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo '<h1>500 Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
    }

    /**
     * Converts warnings/notices that aren't suppressed by error_reporting()
     * into exceptions, so they flow through the same handling path above.
     *
     * @throws ErrorException
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}
