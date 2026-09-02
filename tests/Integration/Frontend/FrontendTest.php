<?php

declare(strict_types=1);

namespace Tests\Integration\Frontend;

use MicroPHP\Application;
use MicroPHP\Http\Request;
use MicroPHP\Router;
use PHPUnit\Framework\TestCase;

final class FrontendTest extends TestCase
{
    public function testRootStaticNestedDynamicAndMissingPages(): void
    {
        $router = new Router();
        self::assertSame(200, $router->dispatch(Request::create('GET', '/'))->status());
        self::assertSame(200, $router->dispatch(Request::create('GET', '/admin/users/1'))->status());
        self::assertSame(404, $router->dispatch(Request::create('GET', '/definitely-missing'))->status());
    }

    public function testFrontendPostRequiresCsrfBeforePageExecutes(): void
    {
        $response = (new Router())->dispatch(Request::create('POST', '/user/create'));
        self::assertSame(419, $response->status());
        self::assertArrayNotHasKey('user', $_SESSION);
    }

    public function testHeadAssetPreservesHeadersAndRemovesBody(): void
    {
        $response = (new Application(app()))->handle(Request::create('HEAD', '/assets/application/css/global.css'));
        self::assertSame(200, $response->status());
        self::assertSame('', $response->body());
        self::assertStringContainsString('text/css', (string) $response->header('content-type'));
    }
}
