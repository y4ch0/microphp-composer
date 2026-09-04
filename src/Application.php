<?php

declare(strict_types=1);

namespace MicroPHP;

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

final class Application
{
    public function __construct(
        private readonly Container $container,
        private readonly ?Router $router = null,
        private readonly ?Api $api = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $assetResponse = $this->assetResponse($request);
            if ($assetResponse !== null) {
                return $this->finalize($request, $assetResponse);
            }

            if ($this->isApiRequest($request) && defined('API_SERVICE_ENABLED') && API_SERVICE_ENABLED === true) {
                return $this->finalize($request, (new ApiDispatcher($this->api ?? new Api($this->container)))->dispatch($request));
            }

            return $this->finalize($request, (new PageDispatcher($this->router ?? new Router($request)))->dispatch($request));
        } catch (\Throwable $e) {
            $response = (new ExceptionHandler(
                $this->container->make(Logger::class),
                defined('APP_DEBUG') && APP_DEBUG === true
            ))->render($e, $request);
            return $this->finalize($request, $response);
        }
    }

    private function finalize(Request $request, Response $response): Response
    {
        if (!defined('SECURITY_HEADERS_ENABLED') || SECURITY_HEADERS_ENABLED === true) {
            $response = $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

            $csp = defined('CONTENT_SECURITY_POLICY') ? trim((string) CONTENT_SECURITY_POLICY) : '';
            if ($csp !== '') {
                $response = $response->withHeader('Content-Security-Policy', $csp);
            }

            if (defined('HSTS_ENABLED') && HSTS_ENABLED === true && $this->isSecureRequest($request)) {
                $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000');
            }
        }

        return $request->method() === 'HEAD' ? $response->withoutBody() : $response;
    }

    private function isSecureRequest(Request $request): bool
    {
        $https = strtolower((string) $request->server('HTTPS', ''));

        return ($https !== '' && $https !== 'off') || (int) $request->server('SERVER_PORT', 0) === 443;
    }

    private function isApiRequest(Request $request): bool
    {
        return ($request->segments()[0] ?? null) === 'api';
    }

    private function assetResponse(Request $request): ?Response
    {
        $path = '/' . $request->path();
        $assetRoots = [
            '/assets/application/' => defined('APP_ASSETS_PATH') ? APP_ASSETS_PATH : ROOT_PATH . '/app/assets',
            '/assets/pages/' => defined('PAGES_PATH') ? PAGES_PATH : ROOT_PATH . '/app/pages',
            '/assets/components/' => defined('COMPONENTS_PATH') ? COMPONENTS_PATH : ROOT_PATH . '/app/components',
            '/assets/' => defined('PUBLIC_ASSETS_PATH') ? PUBLIC_ASSETS_PATH : ROOT_PATH . '/public/assets',
        ];

        foreach ($assetRoots as $prefix => $root) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }

            if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
                return Response::text('Method Not Allowed', 405)
                    ->withHeader('Allow', 'GET, HEAD');
            }

            $file = $this->resolveAssetFile($root, substr($path, strlen($prefix)));
            if ($file === null) {
                return Response::text('Asset not found.', 404);
            }

            $body = file_get_contents($file);
            if ($body === false) {
                return Response::text('Asset not found.', 404);
            }

            $response = (new Response($body))
                ->withHeader('Content-Type', $this->assetContentType($file))
                ->withHeader('Cache-Control', 'public, max-age=3600');

            return $request->method() === 'HEAD' ? $response->withoutBody() : $response;
        }

        return null;
    }

    private function resolveAssetFile(string $root, string $relativePath): ?string
    {
        $rootPath = realpath($root);
        if ($rootPath === false || !is_dir($rootPath)) {
            return null;
        }

        $segments = explode('/', trim($relativePath, '/'));
        if ($segments === [''] || $segments === []) {
            return null;
        }

        $currentPath = $rootPath;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $rawSegment) {
            $segment = $this->normalizeAssetSegment($rawSegment);
            if ($segment === null) {
                return null;
            }

            $nextPath = $this->findChildPath($currentPath, $segment);
            if ($nextPath === null) {
                return null;
            }

            if ($index === $lastIndex) {
                if (!$this->isAllowedAsset($nextPath) || !$this->pathIsInside($nextPath, $rootPath)) {
                    return null;
                }

                return $nextPath;
            }

            if (!is_dir($nextPath)) {
                return null;
            }

            $currentPath = $nextPath;
        }

        return null;
    }

    private function normalizeAssetSegment(string $segment): ?string
    {
        if ($segment === '' || str_contains($segment, "\0")) {
            return null;
        }

        $decoded = $segment;
        for ($i = 0; $i < 3; $i++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        if (
            $decoded === '' ||
            $decoded === '.' ||
            $decoded === '..' ||
            str_contains($decoded, "\0") ||
            str_contains($decoded, '/') ||
            str_contains($decoded, '\\')
        ) {
            return null;
        }

        return $decoded;
    }

    private function findChildPath(string $directory, string $name): ?string
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry !== $name) {
                continue;
            }

            $path = realpath($directory . DIRECTORY_SEPARATOR . $entry);

            return $path === false ? null : $path;
        }

        return null;
    }

    private function isAllowedAsset(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
            'css',
            'js',
            'svg',
            'png',
            'jpg',
            'jpeg',
            'gif',
            'webp',
            'ico',
            'woff',
            'woff2',
            'ttf',
            'eot',
            'map',
            'txt',
            'webmanifest',
        ], true);
    }

    private function assetContentType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'map' => 'application/json',
            'webmanifest' => 'application/manifest+json',
            default => 'text/plain; charset=UTF-8',
        };
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
}
