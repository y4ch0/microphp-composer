<?php

declare(strict_types=1);

/**
 * MicroPHP API router core.
 */

namespace MicroPHP;

use Closure;
use InvalidArgumentException;
use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\Middleware\CorsMiddleware;
use MicroPHP\Http\HttpException;
use MicroPHP\Http\MiddlewarePipeline;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Routing\MethodResolver;
use MicroPHP\Routing\MiddlewareResolver;
use MicroPHP\Routing\RouteResolver;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Throwable;

class Api
{
    /** @var array<int,array<string,mixed>> */
    private static array $routes = [];
    /** @var array<int,MiddlewareInterface|callable> */
    private static array $registeredMiddleware = [];
    /** @var array<string,true> */
    private static array $loadedRouteDirectories = [];

    private static array $defaultCorsConfig = [
        'allowed_origins' => [],
        'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'max_age' => 86400,
    ];

    public function __construct(
        private readonly ?Container $container = null,
        private readonly ?RouteResolver $routeResolver = null,
        private readonly ?MethodResolver $methodResolver = null,
    ) {
    }

    private function container(): Container
    {
        if ($this->container !== null) {
            return $this->container;
        }

        return function_exists('app') ? app() : new Container();
    }

    private function routeResolver(): RouteResolver
    {
        return $this->routeResolver ?? new RouteResolver();
    }

    private function methodResolver(): MethodResolver
    {
        return $this->methodResolver ?? new MethodResolver();
    }

    private static function routesPath(): string
    {
        return defined('API_ROUTES_PATH') ? API_ROUTES_PATH : ROOT_PATH . '/app/api';
    }

    /**
     * Register global API middleware from application code.
     */
    public static function middleware(mixed $middleware): void
    {
        self::$registeredMiddleware = array_merge(
            self::$registeredMiddleware,
            MiddlewarePipeline::normalize($middleware, 'Api::middleware')
        );
    }

    /**
     * Alias for Api::middleware().
     */
    public static function pipe(mixed $middleware): void
    {
        self::middleware($middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function get(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('GET', $path, $callback, $corsConfig, $middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function head(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('HEAD', $path, $callback, $corsConfig, $middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function post(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('POST', $path, $callback, $corsConfig, $middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function put(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('PUT', $path, $callback, $corsConfig, $middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function patch(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('PATCH', $path, $callback, $corsConfig, $middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function delete(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('DELETE', $path, $callback, $corsConfig, $middleware);
    }

    /** @param array<string,mixed> $corsConfig Route-specific CORS overrides. */
    public static function options(string $path, callable $callback, array $corsConfig = [], mixed $middleware = []): void
    {
        self::addRoute('OPTIONS', $path, $callback, $corsConfig, $middleware);
    }

    /**
     * Register a legacy route definition.
     *
     * @param array<string,mixed> $corsConfig Route-specific CORS configuration overrides.
     */
    private static function addRoute(
        string $method,
        string $path,
        callable $callback,
        array $corsConfig,
        mixed $middleware = []
    ): void {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            @trigger_error('Static Api route registration is deprecated; use filesystem method handlers.', E_USER_DEPRECATED);
        }
        $path = '/' . trim($path, '/');

        self::$routes[] = [
            'method' => strtoupper($method),
            'regex' => self::routeRegex($path),
            'path' => $path,
            'callback' => $callback,
            'cors' => array_merge(self::$defaultCorsConfig, $corsConfig),
            'middleware' => MiddlewarePipeline::normalize($middleware, "API route {$method} {$path}"),
        ];
    }

    public function handleRequest(): void
    {
        $this->dispatch()->send();
    }

    public function dispatch(?Request $request = null): Response
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            @trigger_error('Api::dispatch() is deprecated; use ApiDispatcher.', E_USER_DEPRECATED);
        }
        return (new ApiDispatcher($this))->dispatch($request);
    }

    public function dispatchCanonical(Request $request): Response
    {
        $segments = $request->segments();
        $version = (($segments[0] ?? null) === 'api') ? ($segments[1] ?? null) : null;

        if (!$version) {
            return $this->throughMiddleware(
                $request,
                self::$defaultCorsConfig,
                $this->collectMiddleware(null),
                fn (Request $request): Response => Response::error('API version is not specified.', 400, 'API_VERSION_REQUIRED')
            );
        }

        $versionMatch = $this->routeResolver()->resolveStaticChild(self::routesPath(), $version);
        if ($versionMatch === null) {
            return $this->throughMiddleware(
                $request,
                self::$defaultCorsConfig,
                $this->collectMiddleware(null),
                fn (Request $request): Response => Response::error('API version not found.', 404, 'API_VERSION_NOT_FOUND')
            );
        }

        $basePath = $versionMatch->directory;
        $version = $versionMatch->segments[0];

        $resourcePath = '/' . implode('/', array_slice($segments, 2));
        $filesystemResponse = $this->dispatchFilesystemRoute($request, $basePath, $resourcePath, $version);
        if ($filesystemResponse !== null) {
            return $filesystemResponse;
        }

        self::loadRoutes($basePath);

        return $this->dispatchLegacyRoute($request, $resourcePath, $basePath);
    }

    /**
     * Internal API request handling. Used by frontend pages and tests.
     *
     * @param array<string,mixed>|null $data Optional request payload passed to the route handler.
     * @return mixed Decoded JSON response, raw body for non-JSON responses, or null for 204 responses.
     * @throws \Exception When the response is a 4xx/5xx error.
     */
    public static function makeRequest(string $method, string $uri, ?array $data = null): mixed
    {
        $response = self::makeResponse($method, $uri, $data);

        if ($response->status() >= 400) {
            throw new \Exception(self::errorMessageFromResponse($response));
        }

        return self::responsePayload($response);
    }

    /**
     * Internal API dispatch that returns the full Response object.
     *
     * @param array<string,mixed>|null $data Optional request payload passed to the route handler.
     */
    public static function makeResponse(string $method, string $uri, ?array $data = null): Response
    {
        $body = $data === null ? null : (string) json_encode($data, JSON_UNESCAPED_UNICODE);
        $headers = $data === null ? [] : ['Content-Type' => 'application/json'];
        $uri = trim($uri, '/');
        $path = ($uri === 'api' || str_starts_with($uri, 'api/')) ? '/' . $uri : '/api/' . $uri;
        $request = Request::create(
            method: $method,
            path: $path,
            post: $data ?? [],
            headers: $headers,
            rawBody: $body,
        );

        return (new self())->dispatch($request);
    }

    private function dispatchFilesystemRoute(
        Request $request,
        string $basePath,
        string $resourcePath,
        string $version
    ): ?Response {
        $match = $this->routeResolver()->resolve(root: $basePath, path: $resourcePath);
        if ($match === null) {
            return null;
        }

        $explicitMethods = $this->methodResolver()->explicitMethods($match->directory);
        if ($explicitMethods === []) {
            return null;
        }

        $routeRequest = $request->withRouteParams($match->params);
        $middleware = $this->collectMiddleware($version, [], $basePath, $match->directory);

        return $this->throughMiddleware(
            $routeRequest,
            self::$defaultCorsConfig,
            $middleware,
            function (Request $request) use ($match): Response {
                return $this->dispatchFilesystemMethod($match->directory, $request);
            }
        );
    }

    private function dispatchFilesystemMethod(string $directory, Request $request): Response
    {
        $method = $request->method();
        $methodResolver = $this->methodResolver();
        $handlerFile = $methodResolver->resolve($directory, $method);

        if ($handlerFile !== null) {
            $response = $this->handleFilesystemHandler($handlerFile, $request);

            return $method === 'HEAD' ? $response->withoutBody() : $response;
        }

        $allowedMethods = $methodResolver->allowedMethods($directory);

        if ($method === 'OPTIONS') {
            return $this->automaticOptionsResponse($allowedMethods);
        }

        if ($method === 'HEAD' && $methodResolver->exists($directory, 'GET')) {
            return $this->handleFilesystemHandler($methodResolver->resolve($directory, 'GET'), $request)->withoutBody();
        }

        return $this->methodNotAllowedResponse($allowedMethods);
    }

    private function handleFilesystemHandler(string $handlerFile, Request $request): Response
    {
        $handler = require $handlerFile;

        if (!is_callable($handler)) {
            throw new RuntimeException('API handler must return a callable.');
        }

        return $this->invokeHandler($handler, $request);
    }

    private function dispatchLegacyRoute(Request $request, string $resourcePath, string $versionDirectory): Response
    {
        $path = '/' . trim($resourcePath, '/');
        $method = $request->method();
        $legacyInfo = self::legacyRouteInfo($path);

        if ($method === 'OPTIONS' && $legacyInfo !== null) {
            $routeRequest = $request->withRouteParams($legacyInfo['params']);

            return $this->throughMiddleware(
                $routeRequest,
                $legacyInfo['cors'],
                $this->collectMiddleware($versionDirectory),
                fn (Request $request): Response => $this->automaticOptionsResponse($legacyInfo['methods'])
            );
        }

        [$matchedRoute, $params] = self::findRoute($method, $path);
        $headFallback = false;

        if (!$matchedRoute && $method === 'HEAD') {
            [$matchedRoute, $params] = self::findRoute('GET', $path);
            $headFallback = $matchedRoute !== null;
        }

        $routeCors = $matchedRoute['cors'] ?? $legacyInfo['cors'] ?? self::$defaultCorsConfig;
        $routeMiddleware = $matchedRoute['middleware'] ?? [];
        $routeRequest = $request->withRouteParams($params ?: ($legacyInfo['params'] ?? []));

        return $this->throughMiddleware(
            $routeRequest,
            $routeCors,
            $this->collectMiddleware($versionDirectory, $routeMiddleware),
            function (Request $request) use ($matchedRoute, $legacyInfo, $headFallback): Response {
                if ($matchedRoute) {
                    $response = $this->handleRoute($matchedRoute, $request);

                    return $headFallback ? $response->withoutBody() : $response;
                }

                if ($legacyInfo !== null) {
                    return $this->methodNotAllowedResponse($legacyInfo['methods']);
                }

                return Response::error('Endpoint not found.', 404, 'ENDPOINT_NOT_FOUND');
            }
        );
    }

    /** @param array<string,mixed> $route */
    private function handleRoute(array $route, Request $request): Response
    {
        $data = $this->requestData($request);
        $result = self::invokeRouteCallback($route['callback'], $request, $data);

        return $this->normalizeRouteResponse($result, $request->method());
    }

    /**
     * @param array<string,mixed> $corsConfig
     * @param array<int,MiddlewareInterface|callable> $middleware
     * @param callable(Request): Response $destination
     */
    private function throughMiddleware(
        Request $request,
        array $corsConfig,
        array $middleware,
        callable $destination
    ): Response {
        $pipeline = new MiddlewarePipeline([CorsMiddleware::fromConfig($corsConfig)]);
        $pipeline->pipeMany($middleware);

        return $pipeline->handle($request, $destination);
    }

    /**
     * @param array<int,MiddlewareInterface|callable> $routeMiddleware
     * @return array<int,MiddlewareInterface|callable>
     */
    private function collectMiddleware(
        ?string $versionDirectory,
        array $routeMiddleware = [],
        ?string $basePath = null,
        ?string $routeDirectory = null
    ): array {
        $fileMiddleware = $basePath !== null && $routeDirectory !== null
            ? $this->inheritedFileMiddleware($basePath, $routeDirectory)
            : $this->fileMiddleware($versionDirectory);

        return array_merge(
            MiddlewarePipeline::normalize(defined('API_MIDDLEWARE') ? API_MIDDLEWARE : [], 'API_MIDDLEWARE'),
            self::$registeredMiddleware,
            $fileMiddleware,
            $routeMiddleware
        );
    }

    /** @return array<int,MiddlewareInterface|callable> */
    private function fileMiddleware(?string $versionDirectory): array
    {
        $root = realpath(self::routesPath());
        if ($root === false) {
            return [];
        }
        return (new MiddlewareResolver())->resolve($root, $versionDirectory ?? $root);
    }

    /** @return array<int,MiddlewareInterface|callable> */
    private function inheritedFileMiddleware(string $basePath, string $routeDirectory): array
    {
        return (new MiddlewareResolver())->resolve(self::routesPath(), $routeDirectory);
    }

    private function requestData(Request $request): ?array
    {
        $post = $request->post();
        if (is_array($post) && $post !== []) {
            return $post;
        }

        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        if ($request->rawBody() === null || $request->rawBody() === '') {
            return null;
        }

        $data = $request->json();
        if ($data === null) {
            throw new HttpException(400, 'INVALID_JSON', 'Invalid JSON format.');
        }

        return $data;
    }

    private function normalizeRouteResponse(mixed $result, string $method): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($method === 'DELETE' && $result === true) {
            return Response::noContent();
        }

        $status = $method === 'POST' && $result ? 201 : 200;

        return Response::json($result, $status);
    }

    private function invokeHandler(callable $handler, Request $request): Response
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($handler));
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Request::class) {
                $arguments[] = $request;
                continue;
            }

            if ($type instanceof ReflectionNamedType && $type->getName() === Container::class) {
                $arguments[] = $this->container();
                continue;
            }

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->container()->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Unable to resolve handler dependency \${$parameter->getName()}.");
        }

        $result = $handler(...$arguments);
        if (!$result instanceof Response) {
            throw new RuntimeException('Route handlers must return Response.');
        }

        return $result;
    }

    private static function invokeRouteCallback(callable $callback, Request $request, ?array $data): mixed
    {
        if (self::callbackWantsRequest($callback)) {
            return $callback($request);
        }

        return $callback($request->routeParams(), $data);
    }

    private static function callbackWantsRequest(callable $callback): bool
    {
        try {
            $reflection = new ReflectionFunction(Closure::fromCallable($callback));
        } catch (Throwable) {
            return false;
        }

        $parameters = $reflection->getParameters();
        if ($parameters === []) {
            return false;
        }

        return self::typeContainsRequest($parameters[0]->getType());
    }

    private static function typeContainsRequest(?ReflectionType $type): bool
    {
        if ($type instanceof ReflectionNamedType) {
            return ltrim($type->getName(), '\\') === Request::class;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if (self::typeContainsRequest($innerType)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recursively loads legacy route files from a given version directory.
     */
    private static function loadRoutes(string $dir): void
    {
        $realDir = realpath($dir);
        if ($realDir === false || !is_dir($realDir)) {
            return;
        }
        if (isset(self::$loadedRouteDirectories[$realDir])) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (
                $item->isFile()
                && self::pathIsInsideStatic($item->getPathname(), $realDir)
                && $item->getExtension() === 'php'
                && $item->getFilename() !== '_middleware.php'
                && !preg_match('/^(GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS)\.php$/', $item->getFilename())
            ) {
                require_once $item->getPathname();
            }
        }

        self::$loadedRouteDirectories[$realDir] = true;
    }

    private static function pathIsInsideStatic(string $path, string $baseDir): bool
    {
        $realPath = realpath($path);
        $realBase = realpath($baseDir);
        if ($realPath === false || $realBase === false) {
            return false;
        }

        $realPath = rtrim($realPath, DIRECTORY_SEPARATOR);
        $realBase = rtrim($realBase, DIRECTORY_SEPARATOR);

        return $realPath === $realBase
            || str_starts_with($realPath . DIRECTORY_SEPARATOR, $realBase . DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{0: ?array<string,mixed>, 1: array<string,string>}
     */
    private static function findRoute(string $method, string $path): array
    {
        $path = '/' . trim($path, '/');

        foreach (self::$routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                return [$route, self::routeParamsFromMatches($matches)];
            }
        }

        return [null, []];
    }

    /**
     * @return array{methods:string[], params:array<string,string>, cors:array<string,mixed>}|null
     */
    private static function legacyRouteInfo(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        $methods = [];
        $params = [];
        $cors = self::$defaultCorsConfig;

        foreach (self::$routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if ($methods === []) {
                $params = self::routeParamsFromMatches($matches);
                $cors = $route['cors'];
            }

            $methods[] = $route['method'];
        }

        if ($methods === []) {
            return null;
        }

        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        if (!in_array('OPTIONS', $methods, true)) {
            $methods[] = 'OPTIONS';
        }

        return [
            'methods' => self::sortLegacyMethods($methods),
            'params' => $params,
            'cors' => $cors,
        ];
    }

    /** @param array<int|string,mixed> $matches */
    private static function routeParamsFromMatches(array $matches): array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    /** @param string[] $methods */
    private static function sortLegacyMethods(array $methods): array
    {
        $methods = array_values(array_unique($methods));
        $order = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

        usort(
            $methods,
            static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true)
        );

        return $methods;
    }

    private static function routeRegex(string $path): string
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '#^/$#';
        }

        $segments = explode('/', $trimmed);
        $regexSegments = array_map(static function (string $segment): string {
            if (preg_match('/^:([A-Za-z0-9_]+)$/', $segment, $matches)) {
                return '(?<' . $matches[1] . '>[^/]+)';
            }

            return preg_quote($segment, '#');
        }, $segments);

        return '#^/' . implode('/', $regexSegments) . '$#';
    }

    /** @param string[] $allowedMethods */
    private function automaticOptionsResponse(array $allowedMethods): Response
    {
        return Response::noContent()
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /** @param string[] $allowedMethods */
    private function methodNotAllowedResponse(array $allowedMethods): Response
    {
        return Response::error('Method Not Allowed', 405, 'METHOD_NOT_ALLOWED')
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }

    private static function responsePayload(Response $response): mixed
    {
        if ($response->status() === 204 || $response->body() === '') {
            return null;
        }

        $contentType = self::responseHeader($response, 'Content-Type', '');
        if (str_contains(strtolower($contentType), 'application/json')) {
            $decoded = json_decode($response->body(), true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $response->body();
    }

    private static function errorMessageFromResponse(Response $response): string
    {
        $payload = self::responsePayload($response);

        if (is_array($payload) && isset($payload['error']['message']) && is_string($payload['error']['message'])) {
            return $payload['error']['message'];
        }

        return 'API request failed with status ' . $response->status() . '.';
    }

    private static function responseHeader(Response $response, string $name, ?string $default = null): ?string
    {
        foreach ($response->headers() as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    private function pathIsInside(string $path, string $baseDir): bool
    {
        $realPath = realpath($path);
        $realBaseDir = realpath($baseDir);
        if ($realPath === false || $realBaseDir === false) {
            return false;
        }

        $realPath = rtrim($realPath, DIRECTORY_SEPARATOR);
        $realBaseDir = rtrim($realBaseDir, DIRECTORY_SEPARATOR);

        return $realPath === $realBaseDir
            || str_starts_with($realPath . DIRECTORY_SEPARATOR, $realBaseDir . DIRECTORY_SEPARATOR);
    }

    /** @deprecated Attach CsrfMiddleware to cookie-authenticated APIs. */
    public static function verifyCsrf(?Request $request = null): bool
    {
        $request ??= Request::fromGlobals();
        $postToken = $request->post('_token');
        $token = is_string($postToken) ? $postToken : $request->header('X-CSRF-Token');
        return (function_exists('app') ? app(\MicroPHP\Security\Csrf::class) : new \MicroPHP\Security\Csrf())->validate($token);
    }
}
