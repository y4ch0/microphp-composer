<?php
/**
 * MicroPHP Framework
 * Runs a request through a stack of middleware before reaching the
 * destination handler.
 */

namespace MicroPHP\Http;

final class MiddlewarePipeline
{
    /** @var array<int,MiddlewareInterface|callable> */
    private array $middleware = [];

    /** @param iterable<int,MiddlewareInterface|callable> $middleware */
    public function __construct(iterable $middleware = [])
    {
        $this->pipeMany($middleware);
    }

    /**
     * @param MiddlewareInterface|callable(Request,callable):Response $middleware
     */
    public function pipe(MiddlewareInterface|callable $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /** @param iterable<int,MiddlewareInterface|callable> $middleware */
    public function pipeMany(iterable $middleware): self
    {
        foreach ($middleware as $entry) {
            $this->pipe($entry);
        }

        return $this;
    }

    /**
     * Normalize one middleware entry, a list of entries, or class-string entries.
     *
     * @return array<int,MiddlewareInterface|callable>
     */
    public static function normalize(mixed $middleware, string $source = 'middleware'): array
    {
        if ($middleware === null || $middleware === []) {
            return [];
        }

        if (self::isSingleMiddleware($middleware)) {
            return [self::resolve($middleware, $source)];
        }

        if (!is_iterable($middleware)) {
            throw new \RuntimeException("{$source} must be middleware or a list of middleware.");
        }

        $normalized = [];
        foreach ($middleware as $entry) {
            $normalized[] = self::resolve($entry, $source);
        }

        return $normalized;
    }

    /**
     * @param callable(Request): Response $destination Final handler.
     */
    public function handle(Request $request, callable $destination): Response
    {
        $chain = array_reduce(
            array_reverse($this->middleware),
            static fn (callable $next, MiddlewareInterface|callable $middleware): \Closure
                => static fn (Request $req): Response => $middleware instanceof MiddlewareInterface
                    ? $middleware->handle($req, $next)
                    : $middleware($req, $next),
            $destination
        );

        return $chain($request);
    }

    private static function isSingleMiddleware(mixed $middleware): bool
    {
        return $middleware instanceof MiddlewareInterface
            || is_callable($middleware)
            || self::isMiddlewareClass($middleware);
    }

    /**
     * @return MiddlewareInterface|callable
     */
    private static function resolve(mixed $middleware, string $source): MiddlewareInterface|callable
    {
        if ($middleware instanceof MiddlewareInterface || is_callable($middleware)) {
            return $middleware;
        }

        if (self::isMiddlewareClass($middleware)) {
            return \function_exists('app')
                ? \app($middleware)
                : new $middleware();
        }

        throw new \RuntimeException("{$source} contains an invalid middleware entry.");
    }

    private static function isMiddlewareClass(mixed $middleware): bool
    {
        return is_string($middleware)
            && class_exists($middleware)
            && is_subclass_of($middleware, MiddlewareInterface::class);
    }
}
