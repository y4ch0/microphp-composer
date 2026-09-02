<?php

declare(strict_types=1);

namespace MicroPHP\Http\Middleware;

use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Router;

/** @deprecated Adapter for legacy _guard.php files; migrate the file to _middleware.php. */
final class GuardMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Router $router, private readonly mixed $guard) {}

    public function handle(Request $request, callable $next): Response
    {
        $result = ($this->guard)($this->router, $request->routeParams());
        if ($result instanceof Response) { return $result; }
        return $result === true ? $next($request) : $this->router->forbiddenResponse();
    }
}
