<?php
/**
 * MicroPHP Framework
 * Applies CORS headers and short-circuits OPTIONS preflight requests.
 */

namespace MicroPHP\Http\Middleware;

use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowedOrigins Exact origins, or ['*'] to allow any.
     * @param string[] $allowedMethods
     * @param string[] $allowedHeaders
     */
    public function __construct(
        private array $allowedOrigins = [],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization', 'X-CSRF-Token'],
        private int $maxAge = 86400,
    ) {
    }

    /** @param array<string,mixed> $config */
    public static function fromConfig(array $config): self
    {
        return new self(
            allowedOrigins: $config['allowed_origins'] ?? [],
            allowedMethods: $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowedHeaders: $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-CSRF-Token'],
            maxAge: (int) ($config['max_age'] ?? 86400),
        );
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin', '');

        $response = $next($request);

        if ($this->allowedOrigins === ['*']) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        } elseif ($origin !== '' && in_array($origin, $this->allowedOrigins, true)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
    }
}
