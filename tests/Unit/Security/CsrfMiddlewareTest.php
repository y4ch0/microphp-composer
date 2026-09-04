<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use MicroPHP\Http\Middleware\CsrfMiddleware;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION = [];
    }

    public function testValidFormAndHeaderTokensPass(): void
    {
        $csrf = new Csrf();
        $token = $csrf->token();
        $middleware = new CsrfMiddleware($csrf);
        foreach ([
            Request::create('POST', '/save', post: ['_token' => $token]),
            Request::create('DELETE', '/save', headers: ['X-CSRF-Token' => $token]),
        ] as $request) {
            $response = $middleware->handle($request, fn (): Response => Response::text('ran'));
            self::assertSame(200, $response->status());
        }
    }

    public function testMissingInvalidAndRequestedWithTokensShortCircuit(): void
    {
        $csrf = new Csrf();
        $csrf->token();
        $middleware = new CsrfMiddleware($csrf);
        foreach ([
            Request::create('POST', '/save'),
            Request::create('PUT', '/save', post: ['_token' => 'invalid']),
            Request::create('PATCH', '/save', headers: ['X-Requested-With' => 'XMLHttpRequest']),
        ] as $request) {
            $ran = false;
            $response = $middleware->handle($request, function () use (&$ran): Response { $ran = true; return Response::text('ran'); });
            self::assertSame(419, $response->status());
            self::assertFalse($ran);
        }
    }

    public function testSafeMethodsDoNotRequireToken(): void
    {
        $response = (new CsrfMiddleware(new Csrf()))->handle(
            Request::create('GET', '/form'), fn (): Response => Response::text('ok')
        );
        self::assertSame(200, $response->status());
    }

    public function testTokenCanBeRotatedAfterPrivilegeChanges(): void
    {
        $csrf = new Csrf();
        $original = $csrf->token();
        $rotated = $csrf->rotate();

        self::assertNotSame($original, $rotated);
        self::assertFalse($csrf->validate($original));
        self::assertTrue($csrf->validate($rotated));
    }
}
