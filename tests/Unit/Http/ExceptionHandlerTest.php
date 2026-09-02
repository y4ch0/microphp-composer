<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use MicroPHP\ExceptionHandler;
use MicroPHP\Http\Request;
use MicroPHP\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionHandlerTest extends TestCase
{
    public function testFrontendErrorsAreSafeAndOriginalContextIsLogged(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'microphp-log-');
        $secret = 'password=fake SQL SELECT /private/app.php';
        $exception = new RuntimeException($secret);
        $response = (new ExceptionHandler(new Logger($log), false))->render($exception, Request::create('GET', '/page'));
        self::assertSame(500, $response->status());
        self::assertStringContainsString('Internal Server Error', $response->body());
        self::assertStringNotContainsString($secret, $response->body());
        $logged = file_get_contents($log);
        self::assertStringContainsString($secret, $logged);
        self::assertStringContainsString(RuntimeException::class, $logged);
        self::assertStringContainsString('trace', $logged);
        @unlink($log);
    }
}
