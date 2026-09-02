<?php

declare(strict_types=1);

namespace MicroPHP;

use ErrorException;
use MicroPHP\Http\HttpException;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use Throwable;

final class ExceptionHandler
{
    public function __construct(private Logger $logger, private bool $debug = false) {}

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
    }

    public function render(Throwable $e, Request $request): Response
    {
        $this->logger->error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $status = $e instanceof HttpException ? $e->status : 500;
        $code = $e instanceof HttpException ? $e->errorCode : 'INTERNAL_SERVER_ERROR';
        $message = $e instanceof HttpException ? $e->getMessage() : 'Internal Server Error';
        $isApi = ($request->segments()[0] ?? null) === 'api';

        if ($isApi) {
            $response = Response::error($message, $status, $code);
            if ($this->debug && !$e instanceof HttpException) {
                $response = Response::json([
                    'error' => [
                        'code' => $code,
                        'message' => $message,
                        'diagnostic' => ['exception' => get_class($e), 'file' => basename($e->getFile()), 'line' => $e->getLine()],
                    ],
                ], $status);
            }
            return $response;
        }

        $diagnostic = $this->debug && !$e instanceof HttpException
            ? '<p>Exception: ' . self::escape(get_class($e)) . ' in ' . self::escape(basename($e->getFile())) . ':' . $e->getLine() . '</p>'
            : '';

        return Response::html(
            '<!doctype html><html><head><meta charset="UTF-8"><title>' . self::escape($message)
            . '</title></head><body><h1>' . self::escape($message) . '</h1>' . $diagnostic . '</body></html>',
            $status
        );
    }

    public function handleException(Throwable $e): void
    {
        $this->render($e, Request::fromGlobals())->send();
    }

    /** @throws ErrorException */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
