<?php

declare(strict_types=1);

namespace MicroPHP\Routing;

use MicroPHP\Http\MiddlewarePipeline;

final class MiddlewareResolver
{
    /** @return array<int,\MicroPHP\Http\MiddlewareInterface|callable> */
    public function resolve(string $root, string $startDirectory, string $filename = '_middleware.php'): array
    {
        $root = realpath($root);
        $start = realpath($startDirectory);
        if ($root === false || $start === false || !$this->inside($start, $root)) {
            return [];
        }
        $directories = [];
        for ($current = $start; $this->inside($current, $root); $current = dirname($current)) {
            array_unshift($directories, $current);
            if ($current === $root) { break; }
        }
        $middleware = [];
        foreach ($directories as $directory) {
            $file = $directory . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($file) || !$this->inside((string) realpath($file), $root)) { continue; }
            $config = include $file;
            $override = is_array($config) && array_key_exists('middleware', $config) && (bool) ($config['override'] ?? false);
            if (is_array($config) && array_key_exists('middleware', $config)) { $config = $config['middleware']; }
            if ($override) { $middleware = []; }
            $middleware = array_merge($middleware, MiddlewarePipeline::normalize($config, $file));
        }
        return $middleware;
    }

    private function inside(string $path, string $root): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        return $path === $root || str_starts_with($path . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR);
    }
}
