<?php

declare(strict_types=1);

namespace MicroPHP\Http;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $post @param array<string,mixed> $server
     *  @param array<string,string> $headers @param array<string,mixed> $cookies @param array<string,mixed> $files
     *  @param array<string,string> $routeParams @param array<string,mixed>|null $json */
    private function __construct(
        private readonly string $method, private readonly string $path,
        private readonly array $query, private readonly array $post, private readonly array $server,
        private readonly array $headers, private readonly ?string $rawBody,
        private readonly array $cookies, private readonly array $files,
        private readonly array $routeParams, private readonly ?array $json,
    ) {}

    public static function capture(): self { return self::fromGlobals(); }

    public static function fromGlobals(): self
    {
        return self::create(
            method: (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), path: (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            query: $_GET, post: $_POST, server: $_SERVER, headers: self::collectHeadersFromServer($_SERVER),
            rawBody: file_get_contents('php://input') ?: null, cookies: $_COOKIE, files: $_FILES,
        );
    }

    /** @param array<string,mixed> $query @param array<string,mixed> $post @param array<string,mixed> $server
     *  @param array<string,string> $headers @param array<string,mixed> $cookies @param array<string,mixed> $files
     *  @param array<string,string> $routeParams */
    public static function create(
        string $method = 'GET', string $path = '/', array $query = [], array $post = [], array $server = [],
        array $headers = [], ?string $rawBody = null, array $routeParams = [], array $cookies = [], array $files = [],
    ): self {
        $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?? '/');
        $queryString = parse_url($path, PHP_URL_QUERY);
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $fromPath);
            $query = array_merge($fromPath, $query);
        }
        $normalizedPath = trim($pathOnly, '/');
        $normalizedMethod = strtoupper($method);
        $server = array_merge(['REQUEST_METHOD' => $normalizedMethod, 'REQUEST_URI' => '/' . $normalizedPath], $server);
        $json = null;
        if ($rawBody !== null && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $json = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
        }
        return new self($normalizedMethod, $normalizedPath, $query, $post, $server,
            self::normalizeHeaders($headers), $rawBody, $cookies, $files, $routeParams, $json);
    }

    /** @param array<string,string> $routeParams */
    public function withRouteParams(array $routeParams): self
    {
        return new self($this->method, $this->path, $this->query, $this->post, $this->server,
            $this->headers, $this->rawBody, $this->cookies, $this->files, $routeParams, $this->json);
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    /** @return string[] */ public function segments(): array { return $this->path === '' ? [] : explode('/', $this->path); }
    public function query(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->query : ($this->query[$key] ?? $default); }
    public function post(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->post : ($this->post[$key] ?? $default); }
    public function server(?string $key = null, mixed $default = null): mixed { return $key === null ? $this->server : ($this->server[$key] ?? $default); }
    /** @return array<string,string> */ public function headers(): array { return $this->headers; }
    public function rawBody(): ?string { return $this->rawBody; }
    public function route(string $name, mixed $default = null): mixed { return $this->routeParams[$name] ?? $default; }
    /** @return array<string,string> */ public function routeParams(): array { return $this->routeParams; }
    /** @return array<string,mixed>|null */ public function json(): ?array { return $this->json; }
    public function cookie(string $name, mixed $default = null): mixed { return $this->cookies[$name] ?? $default; }
    /** @return array<string,mixed> */ public function cookies(): array { return $this->cookies; }
    public function file(string $name, mixed $default = null): mixed { return $this->files[$name] ?? $default; }
    /** @return array<string,mixed> */ public function files(): array { return $this->files; }
    public function header(string $name, ?string $default = null): ?string { return $this->headers[strtolower($name)] ?? $default; }

    /** Submitted values only: POST, query, then JSON. Route parameters stay separate. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? ($this->json[$key] ?? $default);
    }

    /** @deprecated Use input() and route() explicitly. */
    public function legacyInput(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $this->input($key, $default);
    }

    public function isMethod(string $method): bool { return $this->method === strtoupper($method); }

    /** @param array<string,mixed> $server @return array<string,string> */
    private static function collectHeadersFromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $name => $value) {
            $name = (string) $name;
            if (str_starts_with($name, 'HTTP_')) {
                $headers[substr($name, 5)] = (string) $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headers[$name] = (string) $value;
            }
        }
        return self::normalizeHeaders($headers);
    }

    /** @param array<string,mixed> $headers @return array<string,string> */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower(str_replace('_', '-', (string) $name))] = (string) $value;
        }
        return $normalized;
    }

    /** @deprecated Prefer the explicit accessor methods. */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'method' => $this->method, 'path' => $this->path, 'query' => $this->query, 'post' => $this->post,
            'server' => $this->server, 'headers' => $this->headers, 'rawBody' => $this->rawBody,
            'routeParams' => $this->routeParams, 'cookies' => $this->cookies, 'files' => $this->files, default => null,
        };
    }
}
