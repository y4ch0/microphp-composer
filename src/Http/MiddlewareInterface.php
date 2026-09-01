<?php
/**
 * MicroPHP Framework
 * Middleware contract, in the spirit of PSR-15 but using a plain callable
 * for $next instead of a RequestHandlerInterface object — avoids pulling in
 * psr/http-server-middleware as a dependency for a one-method interface.
 */

namespace MicroPHP\Http;

interface MiddlewareInterface
{
    /**
     * @param Request $request Incoming request.
     * @param callable(Request): Response $next Calls the next layer in the pipeline.
     */
    public function handle(Request $request, callable $next): Response;
}
