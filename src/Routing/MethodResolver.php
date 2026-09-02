<?php

declare(strict_types=1);

namespace MicroPHP\Routing;

final class MethodResolver
{
    private const METHOD_FILES = [
        'GET' => 'GET.php',
        'HEAD' => 'HEAD.php',
        'POST' => 'POST.php',
        'PUT' => 'PUT.php',
        'PATCH' => 'PATCH.php',
        'DELETE' => 'DELETE.php',
        'OPTIONS' => 'OPTIONS.php',
    ];

    public function resolve(string $directory, string $method): ?string
    {
        $filename = self::METHOD_FILES[strtoupper($method)] ?? null;
        if ($filename === null) {
            return null;
        }

        $base = realpath($directory);
        $file = realpath(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename);
        if ($base === false || $file === false || !is_file($file)) {
            return null;
        }
        $base = rtrim($base, DIRECTORY_SEPARATOR);

        return str_starts_with($file . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR) ? $file : null;
    }

    public function exists(string $directory, string $method): bool
    {
        return $this->resolve($directory, $method) !== null;
    }

    /** @return string[] */
    public function allowedMethods(string $directory): array
    {
        $allowed = [];

        foreach (self::METHOD_FILES as $method => $filename) {
            if ($this->resolve($directory, $method) !== null) {
                $allowed[] = $method;
            }
        }

        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        if ($allowed !== [] && !in_array('OPTIONS', $allowed, true)) {
            $allowed[] = 'OPTIONS';
        }

        return $this->sortMethods($allowed);
    }

    /** @return string[] */
    public function explicitMethods(string $directory): array
    {
        $methods = [];

        foreach (self::METHOD_FILES as $method => $filename) {
            if ($this->resolve($directory, $method) !== null) {
                $methods[] = $method;
            }
        }

        return $this->sortMethods($methods);
    }

    /** @param string[] $methods */
    private function sortMethods(array $methods): array
    {
        $methods = array_values(array_unique($methods));
        $order = array_keys(self::METHOD_FILES);

        usort(
            $methods,
            static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true)
        );

        return $methods;
    }
}
