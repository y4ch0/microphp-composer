<?php

declare(strict_types=1);

namespace MicroPHP;

final class AssetManager
{
    public const PRIORITY_GLOBAL = 10;
    public const PRIORITY_PAGE = 20;
    public const PRIORITY_COMPONENT = 30;

    /** @var array<string,array{url:string,priority:int,order:int}> */ private array $styles = [];
    /** @var array<string,array{url:string,priority:int,order:int}> */ private array $scripts = [];
    /** @var array<string,string> */ private array $roots;
    private int $registrationOrder = 0;

    /** @param array<string,string>|null $roots Filesystem root => public URL root. */
    public function __construct(?array $roots = null)
    {
        $roots ??= [
            (defined('APP_ASSETS_PATH') ? APP_ASSETS_PATH : ROOT_PATH . '/app/assets') => (defined('APP_ASSETS_URL') ? APP_ASSETS_URL : '/assets/application'),
            (defined('PAGES_PATH') ? PAGES_PATH : ROOT_PATH . '/app/pages') => (defined('PAGES_URL') ? PAGES_URL : '/assets/pages'),
            (defined('COMPONENTS_PATH') ? COMPONENTS_PATH : ROOT_PATH . '/app/components') => (defined('COMPONENTS_URL') ? COMPONENTS_URL : '/assets/components'),
            (defined('PUBLIC_ASSETS_PATH') ? PUBLIC_ASSETS_PATH : ROOT_PATH . '/public/assets') => (defined('PUBLIC_ASSETS_URL') ? PUBLIC_ASSETS_URL : '/assets'),
        ];
        $this->roots = [];
        foreach ($roots as $root => $url) {
            $real = realpath($root);
            if ($real !== false && is_dir($real)) {
                $this->roots[rtrim($real, DIRECTORY_SEPARATOR)] = rtrim($url, '/');
            }
        }
    }

    public function registerStyleFile(string $file, int $priority = self::PRIORITY_GLOBAL): void
    {
        $this->registerFile($file, 'css', $priority, $this->styles);
    }

    public function registerScriptFile(string $file, int $priority = self::PRIORITY_GLOBAL): void
    {
        $this->registerFile($file, 'js', $priority, $this->scripts);
    }

    public function registerStyleUrl(string $url, int $priority = self::PRIORITY_GLOBAL): void
    {
        if ($this->isControlledUrl($url)) { $this->registerUrl($url, $priority, $this->styles); }
    }

    public function registerScriptUrl(string $url, int $priority = self::PRIORITY_GLOBAL): void
    {
        if ($this->isControlledUrl($url)) { $this->registerUrl($url, $priority, $this->scripts); }
    }

    public function registerComponentDirectory(string $directory): void
    {
        if (is_file($directory . '/style.css')) {
            $this->registerStyleFile($directory . '/style.css', self::PRIORITY_COMPONENT);
        }
        if (is_file($directory . '/script.js')) {
            $this->registerScriptFile($directory . '/script.js', self::PRIORITY_COMPONENT);
        }
    }

    /** @return string[] */ public function styles(): array { return $this->orderedUrls($this->styles); }
    /** @return string[] */ public function scripts(): array { return $this->orderedUrls($this->scripts); }

    public function stylesHtml(): string
    {
        return implode("\n    ", array_map(
            static fn (string $url): string => '<link rel="stylesheet" href="' . View::escape($url) . '">',
            $this->styles()
        ));
    }

    public function scriptsHtml(): string
    {
        return implode("\n    ", array_map(
            static fn (string $url): string => '<script src="' . View::escape($url) . '" defer></script>',
            $this->scripts()
        ));
    }

    /** @param array<string,array{url:string,priority:int,order:int}> $target */
    private function registerFile(string $file, string $extension, int $priority, array &$target): void
    {
        $real = realpath($file);
        if ($real === false || !is_file($real) || strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== $extension) {
            return;
        }
        foreach ($this->roots as $root => $urlRoot) {
            if ($real !== $root && !str_starts_with($real . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root) + 1));
            $url = $urlRoot . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
            $this->registerEntry($real, $url, $priority, $target);
            return;
        }
    }

    /** @param array<string,array{url:string,priority:int,order:int}> $target */
    private function registerUrl(string $url, int $priority, array &$target): void
    {
        $this->registerEntry('url:' . $url, $url, $priority, $target);
    }

    /** @param array<string,array{url:string,priority:int,order:int}> $target */
    private function registerEntry(string $key, string $url, int $priority, array &$target): void
    {
        if (isset($target[$key])) {
            // If an asset belongs to more than one scope, keep it in the most
            // specific (latest) scope without emitting it twice.
            $target[$key]['priority'] = max($target[$key]['priority'], $priority);
            return;
        }

        $target[$key] = [
            'url' => $url,
            'priority' => $priority,
            'order' => $this->registrationOrder++,
        ];
    }

    /**
     * @param array<string,array{url:string,priority:int,order:int}> $entries
     * @return string[]
     */
    private function orderedUrls(array $entries): array
    {
        $entries = array_values($entries);
        usort($entries, static fn (array $left, array $right): int =>
            [$left['priority'], $left['order']] <=> [$right['priority'], $right['order']]
        );

        return array_column($entries, 'url');
    }

    private function isControlledUrl(string $url): bool
    {
        return str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
