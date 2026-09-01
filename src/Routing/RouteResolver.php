<?php

declare(strict_types=1);

namespace MicroPHP\Routing;

use RuntimeException;

final class RouteResolver
{
    /**
     * Resolve a URL path to a directory under the given filesystem root.
     *
     * @param string[] $defaultSegments Used when resolving an empty path.
     */
    public function resolve(string $root, string $path, array $defaultSegments = []): ?RouteMatch
    {
        $rootPath = realpath($root);
        if ($rootPath === false || !is_dir($rootPath)) {
            return null;
        }

        $segments = $this->pathSegments($path);
        if ($segments === null) {
            return null;
        }

        if ($segments === [] && $defaultSegments !== []) {
            $segments = $defaultSegments;
        }

        $currentPath = $rootPath;
        $params = [];
        $matchedSegments = [];

        foreach ($segments as $rawSegment) {
            $segment = $this->normalizeSegment($rawSegment);
            if ($segment === null) {
                return null;
            }

            $directories = $this->childDirectories($currentPath);
            if (isset($directories[$segment])) {
                $currentPath = $directories[$segment];
                $matchedSegments[] = $segment;
                continue;
            }

            $dynamic = $this->firstDynamicDirectory($directories);
            if ($dynamic === null) {
                return null;
            }

            [$parameterName, $directoryName, $directoryPath] = $dynamic;
            $params[$parameterName] = $segment;
            $currentPath = $directoryPath;
            $matchedSegments[] = $directoryName;
        }

        $this->ensureInsideRoot($rootPath, $currentPath);

        return new RouteMatch($currentPath, $params, $matchedSegments);
    }

    /** @return string[]|null */
    private function pathSegments(string $path): ?array
    {
        $pathOnly = parse_url($path, PHP_URL_PATH);
        if ($pathOnly === false) {
            return null;
        }

        $pathOnly = trim((string) ($pathOnly ?? ''), '/');
        if ($pathOnly === '') {
            return [];
        }

        return explode('/', $pathOnly);
    }

    private function normalizeSegment(string $segment): ?string
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

    /** @return array<string,string> Directory name => real path. */
    private function childDirectories(string $directory): array
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $directories = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $realPath = realpath($path);
            if ($realPath !== false && is_dir($realPath)) {
                $directories[$entry] = $realPath;
            }
        }

        return $directories;
    }

    /**
     * @param array<string,string> $directories
     * @return array{0:string,1:string,2:string}|null Parameter name, directory name, directory path.
     */
    private function firstDynamicDirectory(array $directories): ?array
    {
        foreach ($directories as $directoryName => $directoryPath) {
            $directoryName = (string) $directoryName;
            if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_]*)\]$/', $directoryName, $matches)) {
                return [$matches[1], $directoryName, $directoryPath];
            }
        }

        return null;
    }

    private function ensureInsideRoot(string $root, string $resolved): void
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $resolved = rtrim($resolved, DIRECTORY_SEPARATOR);

        if ($resolved === $root) {
            return;
        }

        if (!str_starts_with($resolved . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Invalid route path.');
        }
    }
}
