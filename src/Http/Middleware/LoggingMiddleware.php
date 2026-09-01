<?php
/**
 * MicroPHP Framework
 * Example middleware: logs method/path/status/duration for each request
 * that passes through the pipeline it's registered on.
 */

namespace MicroPHP\Http\Middleware;

use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Logger;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private Logger $logger)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        $this->logger->info('request handled', [
            'method' => $request->method(),
            'path' => '/' . $request->path(),
            'status' => $response->status(),
            'duration_ms' => $durationMs,
        ]);

        return $response;
    }
}
