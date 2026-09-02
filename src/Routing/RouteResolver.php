<?php

declare(strict_types=1);

namespace MicroPHP\Routing;

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

            $directories = $this->childDirectories($rootPath, $currentPath);
            $dynamic = $this->dynamicDirectory($directories, $currentPath);
            if (isset($directories[$segment])) {
                $currentPath = $directories[$segment];
                $matchedSegments[] = $segment;
                continue;
            }

            if ($dynamic === null) {
                return null;
            }

            [$parameterName, $directoryName, $directoryPath] = $dynamic;
            $params[$parameterName] = $segment;
            $currentPath = $directoryPath;
            $matchedSegments[] = $directoryName;
        }

        return new RouteMatch($currentPath, $params, $matchedSegments);
    }

    /**
     * Resolve exactly one static child directory. Dynamic directories are never
     * considered, making this suitable for trust boundaries such as API versions.
     */
    public function resolveStaticChild(string $root, string $segment): ?RouteMatch
    {
        $rootPath = realpath($root);
        if ($rootPath === false || !is_dir($rootPath)) {
            return null;
        }

        $normalized = $this->normalizeSegment($segment);
        if ($normalized === null) {
            return null;
        }

        $directories = $this->childDirectories($rootPath, $rootPath);
        if (!isset($directories[$normalized])) {
            return null;
        }

        return new RouteMatch($directories[$normalized], [], [$normalized]);
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
        for ($i = 0; $i < 16; $i++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        // Fail closed when an excessively encoded value did not stabilize.
        if (rawurldecode($decoded) !== $decoded) {
            return null;
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
    private function childDirectories(string $root, string $directory): array
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
            if ($realPath !== false && is_dir($realPath) && $this->isInsideRoot($root, $realPath)) {
                $directories[$entry] = $realPath;
            }
        }

        return $directories;
    }

    /**
     * @param array<string,string> $directories
     * @return array{0:string,1:string,2:string}|null Parameter name, directory name, directory path.
     */
    private function dynamicDirectory(array $directories, string $directory): ?array
    {
        $matches = [];
        foreach ($directories as $directoryName => $directoryPath) {
            $directoryName = (string) $directoryName;
            if (preg_match('/^\[([A-Za-z_][A-Za-z0-9_]*)\]$/', $directoryName, $nameMatch)) {
                $matches[] = [$nameMatch[1], $directoryName, $directoryPath];
            }
        }

        if (count($matches) > 1) {
            throw new RoutingConfigurationException("Ambiguous dynamic route directories beneath {$directory}.");
        }

        return $matches[0] ?? null;
    }

    private function isInsideRoot(string $root, string $resolved): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $resolved = rtrim($resolved, DIRECTORY_SEPARATOR);

        if ($resolved === $root) {
            return true;
        }

        return str_starts_with($resolved . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR);
    }
}
