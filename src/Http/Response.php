<?php
/**
 * MicroPHP Framework
 * HTTP response value object (PSR-7-inspired, dependency-free).
 *
 * Building a Response does not touch the network. Headers and body are only
 * emitted by send(), which lets routers and middleware stay testable.
 */

namespace MicroPHP\Http;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function text(string $body, int $status = 200): self
    {
        return (new self($body, $status))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return (new self($body, $status))
            ->withHeader('Content-Type', 'application/json');
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))
            ->withHeader('Location', $url);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        foreach ($headers as $name => $value) {
            $clone->headers[$name] = $value;
        }

        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function withoutBody(): self
    {
        return $this->withBody('');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if (!$this->isEmptyStatus()) {
            echo $this->body;
        }
    }

    private function isEmptyStatus(): bool
    {
        return in_array($this->status, [204, 304], true);
    }
}
