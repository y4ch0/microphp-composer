<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use InvalidArgumentException;
use JsonException;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use PHPUnit\Framework\TestCase;

final class RequestResponseTest extends TestCase
{
    public function testStructuredImmutableRequestAccessAndInputPrecedence(): void
    {
        $request = Request::create('post', '/users/42?q=query', query: ['same' => 'query'], post: ['same' => 'post'],
            headers: ['X-Test' => 'yes'], rawBody: '{"json":"value"}', routeParams: ['same' => 'route', 'id' => '42'],
            cookies: ['sid' => 'abc'], files: ['photo' => ['name' => 'a.jpg']]);
        self::assertSame('POST', $request->method());
        self::assertSame('users/42', $request->path());
        self::assertSame('post', $request->input('same'));
        self::assertSame('route', $request->legacyInput('same'));
        self::assertSame('42', $request->route('id'));
        self::assertSame('value', $request->json()['json']);
        self::assertSame('abc', $request->cookie('sid'));
        self::assertSame('a.jpg', $request->file('photo')['name']);
        self::assertSame('yes', $request->header('x-test'));
    }

    public function testHeadersAreCaseInsensitiveAndValidated(): void
    {
        $response = (new Response())->withHeader('X-Test', 'one')->withHeader('x-test', 'two');
        self::assertCount(1, $response->headers());
        self::assertSame('two', $response->header('X-TEST'));
        $this->expectException(InvalidArgumentException::class);
        $response->withHeader('X-Bad', "value\r\nInjected: yes");
    }

    public function testStatusesAndBodylessResponsesAreEnforced(): void
    {
        self::assertSame('', (new Response('forbidden', 204))->body());
        self::assertSame('', (new Response('forbidden', 304))->body());
        $this->expectException(InvalidArgumentException::class);
        new Response('', 700);
    }

    public function testJsonEncodingFailureIsExplicit(): void
    {
        $this->expectException(JsonException::class);
        Response::json(["bad" => "\xB1\x31"]);
    }

    public function testErrorShape(): void
    {
        $response = Response::error('Internal Server Error', 500, 'INTERNAL_SERVER_ERROR');
        self::assertSame(['error' => ['code' => 'INTERNAL_SERVER_ERROR', 'message' => 'Internal Server Error']], json_decode($response->body(), true));
    }
}
