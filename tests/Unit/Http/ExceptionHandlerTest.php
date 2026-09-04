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

    public function testLogMessagesCannotInjectAdditionalLines(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'microphp-log-');
        $logger = new Logger($log);
        $logger->warning("invalid value\r\n[FORGED] ADMIN: success", ['input' => "one\ntwo"]);

        $contents = (string) file_get_contents($log);
        $lines = array_values(array_filter(explode(PHP_EOL, $contents), static fn (string $line): bool => $line !== ''));
        self::assertCount(1, $lines);
        $entry = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame("invalid value\r\n[FORGED] ADMIN: success", $entry['message']);
        self::assertSame("one\ntwo", $entry['context']['input']);
        @unlink($log);
    }
}
