<?php

declare(strict_types=1);

namespace MicroPHP\Http;

use InvalidArgumentException;
use JsonException;

final class Response
{
    /** @var array<string,array{name:string,value:string}> */
    private array $headers = [];

    public function __construct(private string $body = '', private int $status = 200)
    {
        self::assertStatus($status);
        if ($this->isEmptyStatus()) {
            $this->body = '';
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function text(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** @throws JsonException */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        return (new self($body, $status))->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function error(string $message, int $status, string $code): self
    {
        return self::json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    public static function noContent(int $status = 204): self { return new self('', $status); }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $url);
    }

    public function withHeader(string $name, string $value): self
    {
        self::assertHeader($name, $value);
        $clone = clone $this;
        $clone->headers[strtolower($name)] = ['name' => $name, 'value' => $value];
        return $clone;
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        $response = $this;
        foreach ($headers as $name => $value) {
            $response = $response->withHeader((string) $name, (string) $value);
        }
        return $response;
    }

    public function withStatus(int $status): self
    {
        self::assertStatus($status);
        $clone = clone $this;
        $clone->status = $status;
        if ($clone->isEmptyStatus()) {
            $clone->body = '';
        }
        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $clone->isEmptyStatus() ? '' : $body;
        return $clone;
    }

    public function withoutBody(): self { return $this->withBody(''); }
    public function status(): int { return $this->status; }
    public function body(): string { return $this->body; }

    /** @return array<string,string> */
    public function headers(): array
    {
        $result = [];
        foreach ($this->headers as $header) {
            $result[$header['name']] = $header['value'];
        }
        return $result;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)]['value'] ?? $default;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers() as $name => $value) {
            header("{$name}: {$value}");
        }
        if (!$this->isEmptyStatus()) {
            echo $this->body;
        }
    }

    private static function assertStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('HTTP status must be between 100 and 599.');
        }
    }

    private static function assertHeader(string $name, string $value): void
    {
        if ($name === '' || preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP header name.');
        }
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException('HTTP header values cannot contain newlines.');
        }
    }

    private function isEmptyStatus(): bool { return in_array($this->status, [204, 304], true); }
}
