<?php

declare(strict_types=1);

namespace MicroPHP;

final class AssetManager
{
    /** @var array<string,string> */ private array $styles = [];
    /** @var array<string,string> */ private array $scripts = [];
    /** @var array<string,string> */ private array $roots;

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

    public function registerStyleFile(string $file): void { $this->registerFile($file, 'css', $this->styles); }
    public function registerScriptFile(string $file): void { $this->registerFile($file, 'js', $this->scripts); }

    public function registerStyleUrl(string $url): void
    {
        if ($this->isControlledUrl($url)) { $this->styles[$url] = $url; }
    }

    public function registerScriptUrl(string $url): void
    {
        if ($this->isControlledUrl($url)) { $this->scripts[$url] = $url; }
    }

    public function registerComponentDirectory(string $directory): void
    {
        if (is_file($directory . '/style.css')) { $this->registerStyleFile($directory . '/style.css'); }
        if (is_file($directory . '/script.js')) { $this->registerScriptFile($directory . '/script.js'); }
    }

    /** @return string[] */ public function styles(): array { return array_values($this->styles); }
    /** @return string[] */ public function scripts(): array { return array_values($this->scripts); }

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

    /** @param array<string,string> $target */
    private function registerFile(string $file, string $extension, array &$target): void
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
            $target[$real] = $url;
            return;
        }
    }

    private function isControlledUrl(string $url): bool
    {
        return str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
