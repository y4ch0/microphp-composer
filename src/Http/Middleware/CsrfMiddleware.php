<?php

declare(strict_types=1);

namespace MicroPHP\Http\Middleware;

use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Security\Csrf;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly Csrf $csrf) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::PROTECTED_METHODS, true)) {
            return $next($request);
        }

        $postToken = $request->post('_token');
        $token = is_string($postToken) ? $postToken : $request->header('X-CSRF-Token');
        if (!$this->csrf->validate($token)) {
            return Response::html('<h1>419 Page Expired</h1><p>Invalid or missing CSRF token.</p>', 419);
        }

        return $next($request);
    }
}
