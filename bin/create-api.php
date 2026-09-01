#!/usr/bin/env php
<?php
/**
 * CLI tool for generating MicroPHP API route handlers.
 *
 * Usage:
 *   php bin/create-api.php /api/v1/users
 *   php bin/create-api.php /api/v1/users/:id
 *   php bin/create-api.php /users --version=v1
 *   php bin/create-api.php /users --dry-run
 */

declare(strict_types=1);

const DEFAULT_API_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require_once dirname(__DIR__) . '/bootstrap/app.php';

$arguments = array_values(array_filter(array_slice($argv, 1), static fn(string $arg): bool => $arg !== ''));
$force = in_array('--force', $arguments, true);
$dryRun = in_array('--dry-run', $arguments, true);
$versionOption = optionValue($arguments, '--version') ?? 'v1';
$url = firstPositionalArgument($arguments);

if ($url === null || in_array($url, ['-h', '--help', 'help'], true)) {
    usage(0);
}

try {
    $route = normalizeApiRoute($url, $versionOption);
    $routeDirectory = apiRouteDirectory($route);
    $legacyRoutes = findExistingLegacyRouteHandlers($route['version'], $route['path']);

    if ($legacyRoutes !== []) {
        throw new RuntimeException(existingLegacyRouteMessage($route['path'], $legacyRoutes));
    }

    $existingMethodFiles = findExistingFilesystemMethodFiles($routeDirectory, DEFAULT_API_METHODS);
    if ($existingMethodFiles !== [] && !$force) {
        throw new RuntimeException(existingFilesystemRouteMessage($route['path'], $existingMethodFiles));
    }

    if (!$dryRun) {
        ensureDirectory($routeDirectory);
    }

    $files = [];
    foreach (DEFAULT_API_METHODS as $method) {
        $path = $routeDirectory . '/' . $method . '.php';
        writeGeneratedFile($path, apiHandlerSource($route, $method), $force, $dryRun, $files);
    }

    echo ($dryRun ? 'API route would be created: ' : 'API route created: ') . "{$routeDirectory}\n";
    echo "Route: /api/{$route['version']}{$route['path']}\n";
    echo "Method files:\n";
    foreach ($files as $file) {
        echo "  - {$file}\n";
    }
    exit(0);
} catch (RuntimeException $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}

/**
 * @param string[] $arguments
 */
function firstPositionalArgument(array $arguments): ?string
{
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '-')) {
            return $argument;
        }
    }

    return null;
}

/**
 * @param string[] $arguments
 */
function optionValue(array $arguments, string $option): ?string
{
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, $option . '=')) {
            return substr($argument, strlen($option) + 1);
        }
    }

    return null;
}

function usage(int $exitCode): never
{
    echo "Usage: php bin/create-api.php <url-path> [--version=v1] [--force] [--dry-run]\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php bin/create-api.php /api/v1/users\n";
    echo "  php bin/create-api.php /api/v1/users/:id\n";
    echo "  php bin/create-api.php /users --version=v2\n";
    exit($exitCode);
}

/**
 * @return array{version: string, path: string, segments: string[], fileSegments: string[]}
 */
function normalizeApiRoute(string $url, string $defaultVersion): array
{
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    $segments = $path === '' ? [] : explode('/', $path);

    $version = trim($defaultVersion, '/');
    if ($version === '') {
        throw new RuntimeException('API version cannot be empty.');
    }
    assertSafeStaticSegment($version, 'API version');

    if (($segments[0] ?? null) === 'api') {
        $version = $segments[1] ?? '';
        if ($version === '') {
            throw new RuntimeException('API URL must include a version, for example /api/v1/users.');
        }
        assertSafeStaticSegment($version, 'API version');
        $segments = array_slice($segments, 2);
    } elseif (isset($segments[0]) && preg_match('/^v[0-9][A-Za-z0-9_-]*$/', $segments[0])) {
        $version = array_shift($segments);
        assertSafeStaticSegment($version, 'API version');
    }

    $routeSegments = [];
    $fileSegments = [];

    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }

        if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches)) {
            $segment = ':' . $matches[1];
        }

        if (str_starts_with($segment, ':')) {
            $param = substr($segment, 1);
            assertSafeParameter($param);
            $routeSegments[] = ':' . $param;
            $fileSegments[] = '[' . $param . ']';
            continue;
        }

        assertSafeStaticSegment($segment, 'Route segment');
        $routeSegments[] = $segment;
        $fileSegments[] = $segment;
    }

    return [
        'version' => $version,
        'path' => '/' . implode('/', $routeSegments),
        'segments' => $routeSegments,
        'fileSegments' => $fileSegments,
    ];
}

function assertSafeStaticSegment(string $segment, string $label): void
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
        throw new RuntimeException("{$label} contains unsupported characters: {$segment}");
    }
}

function assertSafeParameter(string $parameter): void
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $parameter)) {
        throw new RuntimeException("Route parameter contains unsupported characters: {$parameter}");
    }
}

/**
 * @param array<string,mixed> $route
 */
function apiRouteDirectory(array $route): string
{
    $basePath = rtrim(API_ROUTES_PATH, '/\\') . '/' . $route['version'];
    $fileSegments = $route['fileSegments'];

    if ($fileSegments === []) {
        return $basePath;
    }

    return $basePath . '/' . implode('/', $fileSegments);
}

function ensureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory: {$directory}");
    }
}

/**
 * @param string[] $created
 */
function writeGeneratedFile(string $path, string $contents, bool $force, bool $dryRun, array &$created): void
{
    if (file_exists($path) && !$force) {
        throw new RuntimeException("File already exists: {$path}. Use --force to overwrite it.");
    }

    if ($dryRun) {
        $created[] = $path;
        return;
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Unable to write file: {$path}");
    }

    $created[] = $path;
}

/**
 * @return array<int,array{method: string, file: string}>
 */
function findExistingLegacyRouteHandlers(string $version, string $routePath): array
{
    $basePath = rtrim(API_ROUTES_PATH, '/\\') . '/' . $version;

    if (!is_dir($basePath)) {
        return [];
    }

    $pattern = '/Api::(get|head|post|put|patch|delete|options)\s*\(\s*([\'"])' . preg_quote($routePath, '/') . '\2/i';
    $matches = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        if (preg_match_all($pattern, $contents, $routeMatches)) {
            foreach ($routeMatches[1] as $method) {
                $matches[] = [
                    'method' => strtoupper($method),
                    'file' => $file->getPathname(),
                ];
            }
        }
    }

    return $matches;
}

/**
 * @param string[] $methods
 * @return array<int,array{method: string, file: string}>
 */
function findExistingFilesystemMethodFiles(string $routeDirectory, array $methods): array
{
    if (!is_dir($routeDirectory)) {
        return [];
    }

    $matches = [];
    foreach ($methods as $method) {
        $file = rtrim($routeDirectory, '/\\') . '/' . $method . '.php';
        if (is_file($file)) {
            $matches[] = [
                'method' => $method,
                'file' => $file,
            ];
        }
    }

    return $matches;
}

/**
 * @param array<int,array{method: string, file: string}> $matches
 */
function existingLegacyRouteMessage(string $routePath, array $matches): string
{
    $methods = implode(', ', array_values(array_unique(array_column($matches, 'method'))));
    $files = implode(', ', array_values(array_unique(array_map(
        static fn(array $match): string => relativePath($match['file']),
        $matches
    ))));

    return "Route {$routePath} is already registered for {$methods} in {$files}. Migrate or remove the legacy route before generating filesystem method files.";
}

/**
 * @param array<int,array{method: string, file: string}> $matches
 */
function existingFilesystemRouteMessage(string $routePath, array $matches): string
{
    $methods = implode(', ', array_values(array_unique(array_column($matches, 'method'))));
    $files = implode(', ', array_values(array_unique(array_map(
        static fn(array $match): string => relativePath($match['file']),
        $matches
    ))));

    return "Route {$routePath} already has {$methods} method file(s) in {$files}. Use --force to overwrite them.";
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', strtolower($path));
}

function relativePath(string $path): string
{
    $root = normalizePath(ROOT_PATH) . '/';
    $normalized = normalizePath($path);

    if (str_starts_with($normalized, $root)) {
        return str_replace('\\', '/', substr($path, strlen(ROOT_PATH) + 1));
    }

    return str_replace('\\', '/', $path);
}

/**
 * @param array<string,mixed> $route
 */
function apiHandlerSource(array $route, string $method): string
{
    $fullPath = '/api/' . $route['version'] . $route['path'];
    $resource = trim($route['path'], '/') ?: 'root';
    $action = match ($method) {
        'GET' => 'reading',
        'POST' => 'creating',
        'PUT' => 'replacing',
        'PATCH' => 'partially updating',
        'DELETE' => 'deleting',
        default => 'handling',
    };
    $statusArgument = $method === 'POST' ? ', 201' : '';

    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        return <<<PHP
<?php

declare(strict_types=1);

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

/**
 * {$method} {$fullPath}
 * Example response for {$action} {$resource}.
 */
return function (Request \$request): Response {
    \$data = \$request->json() ?? \$request->post();

    return Response::json([
        'data' => [
            'method' => '{$method}',
            'params' => \$request->routeParams(),
            'body' => \$data,
        ],
        'message' => 'Replace this example with {$action} logic.',
    ]{$statusArgument});
};

PHP;
    }

    return <<<PHP
<?php

declare(strict_types=1);

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

/**
 * {$method} {$fullPath}
 * Example response for {$action} {$resource}.
 */
return function (Request \$request): Response {
    return Response::json([
        'data' => [
            'method' => '{$method}',
            'params' => \$request->routeParams(),
        ],
        'message' => 'Replace this example with {$action} logic.',
    ]);
};

PHP;
}
