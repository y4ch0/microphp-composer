<?php
/**
 * MicroPHP Framework
 * Immutable HTTP request value object (PSR-7-inspired, dependency-free).
 *
 * The request is captured once at the edge of the framework and then passed
 * through routers, route handlers, and middleware as plain data.
 */

namespace MicroPHP\Http;

final class Request
{
    /** @var array<string,mixed>|null Cached decoded JSON body. */
    private ?array $decodedJson = null;
    private bool $jsonDecoded = false;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     * @param array<string,string> $headers
     * @param array<string,string> $routeParams Params extracted by a router.
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $headers,
        private readonly ?string $rawBody,
        private readonly array $routeParams = [],
    ) {
    }

    public static function capture(): self
    {
        return self::fromGlobals();
    }

    public static function fromGlobals(): self
    {
        return self::create(
            method: (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            query: $_GET,
            post: $_POST,
            server: $_SERVER,
            headers: self::collectHeadersFromServer($_SERVER),
            rawBody: file_get_contents('php://input') ?: null,
        );
    }

    /**
     * Build a synthetic request for tests, internal dispatch, and middleware.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     * @param array<string,string> $headers
     * @param array<string,string> $routeParams
     */
    public static function create(
        string $method = 'GET',
        string $path = '/',
        array $query = [],
        array $post = [],
        array $server = [],
        array $headers = [],
        ?string $rawBody = null,
        array $routeParams = [],
    ): self {
        $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?? '/');
        $queryString = parse_url($path, PHP_URL_QUERY);

        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $queryFromPath);
            $query = array_merge($queryFromPath, $query);
        }

        $normalizedPath = trim($pathOnly, '/');
        $normalizedMethod = strtoupper($method);
        $server = array_merge([
            'REQUEST_METHOD' => $normalizedMethod,
            'REQUEST_URI' => '/' . $normalizedPath . ($queryString ? '?' . $queryString : ''),
        ], $server);

        return new self(
            method: $normalizedMethod,
            path: $normalizedPath,
            query: $query,
            post: $post,
            server: $server,
            headers: self::normalizeHeaders($headers),
            rawBody: $rawBody,
            routeParams: $routeParams,
        );
    }

    /**
     * Return a copy of the request with router-extracted params attached.
     *
     * @param array<string,string> $routeParams
     */
    public function withRouteParams(array $routeParams): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: $this->query,
            post: $this->post,
            server: $this->server,
            headers: $this->headers,
            rawBody: $this->rawBody,
            routeParams: $routeParams,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return string[] */
    public function segments(): array
    {
        return $this->path === '' ? [] : explode('/', $this->path);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->server : ($this->server[$key] ?? $default);
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    public function route(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    /** @return array<string,string> */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /** @param array<string,mixed> $server */
    private static function collectHeadersFromServer(array $server): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return $headers === false ? [] : self::normalizeHeaders($headers);
        }

        $headers = [];
        foreach ($server as $name => $value) {
            $name = (string) $name;
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = (string) $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$key] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $headers
     * @return array<string,string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', (string) $name))));
            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    /** Decode the raw body as JSON. Returns null when absent or invalid. */
    public function json(): ?array
    {
        if (!$this->jsonDecoded) {
            $this->jsonDecoded = true;
            if ($this->rawBody !== null && $this->rawBody !== '') {
                $decoded = json_decode($this->rawBody, true);
                $this->decodedJson = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
            }
        }

        return $this->decodedJson;
    }

    /** Look up a value across route params, POST, query string, and JSON body, in that order. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key]
            ?? $this->post[$key]
            ?? $this->query[$key]
            ?? ($this->json()[$key] ?? $default);
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Backward-compatible read access for older applications.
     *
     * @deprecated Prefer method(), path(), query(), post(), server(),
     *             headers(), rawBody(), and routeParams().
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'method' => $this->method,
            'path' => $this->path,
            'query' => $this->query,
            'post' => $this->post,
            'server' => $this->server,
            'headers' => $this->headers,
            'rawBody' => $this->rawBody,
            'routeParams' => $this->routeParams,
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'method',
            'path',
            'query',
            'post',
            'server',
            'headers',
            'rawBody',
            'routeParams',
        ], true);
    }
}
