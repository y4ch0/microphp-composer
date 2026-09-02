<?php

declare(strict_types=1);

namespace MicroPHP;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class View
{
    private static ?string $viewsPath = null;
    private static ?string $cachePath = null;
    private static ?string $componentsPath = null;
    private static ?ViewCache $cache = null;

    public function __construct(private readonly ?AssetManager $assets = null) {}

    public static function configurePaths(?string $viewsPath = null, ?string $cachePath = null, ?string $componentsPath = null): void
    {
        self::$viewsPath = $viewsPath;
        self::$cachePath = $cachePath;
        self::$componentsPath = $componentsPath;
        self::$cache = null;
    }

    public static function render(string $view, array $data = []): string
    {
        return (new self())->renderNamed($view, $data);
    }

    public function renderNamed(string $view, array $data = []): string
    {
        return $this->renderTemplateFile(self::viewToFile($view), $data);
    }

    public static function renderFile(string $file, array $data = []): string
    {
        return (new self())->renderTemplateFile($file, $data);
    }

    public function renderTemplateFile(string $file, array $data = []): string
    {
        $realFile = realpath($file);
        if ($realFile === false || !is_file($realFile)) {
            throw new RuntimeException("View not found: {$file}");
        }
        if (!self::isInsideTemplateRoots($realFile)) {
            throw new RuntimeException('Template is outside the configured view roots.');
        }
        return $this->renderResolvedFile($realFile, $data);
    }

    public static function renderTrustedFile(string $file, array $data = []): string
    {
        $realFile = realpath($file);
        if ($realFile === false || !is_file($realFile)) {
            throw new RuntimeException("View not found: {$file}");
        }
        return (new self())->renderResolvedFile($realFile, $data);
    }

    public static function include(string $view, array $data = []): void
    {
        echo self::render($view, $data);
    }

    public function renderInclude(string $view, array $data = []): void
    {
        echo $this->renderNamed($view, $data);
    }

    public static function component(string $view, array $data = []): void
    {
        (new self())->renderComponent($view, $data);
    }

    public function renderComponent(string $view, array $data = []): void
    {
        $class = Component::resolveClass($view);
        if ($class === null) {
            throw new RuntimeException("Component class not found: {$view}");
        }
        echo $class::renderComponent($data, $this->assets);
    }

    public static function warmCache(): array
    {
        return self::cache()->warmAll(self::allMicroFiles(), [self::class, 'compile']);
    }

    public static function clearCache(): int { return self::cache()->clearAll(); }
    public static function cacheStats(): array { return self::cache()->stats(); }
    public static function compile(string $source): string { return self::compileString($source); }

    private function renderResolvedFile(string $file, array $variables): string
    {
        $cacheFile = self::cache()->resolve($file, [self::class, 'compile']);
        extract($variables, EXTR_SKIP);
        $__view_instance = $this;
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (!defined('MICROPHP_VIEW_CONTEXT')) { define('MICROPHP_VIEW_CONTEXT', true); }
        ob_start();
        try { include $cacheFile; }
        catch (Throwable $e) { ob_end_clean(); throw $e; }
        return ob_get_clean();
    }

    private static function compileString(string $content): string
    {
        $content = preg_replace('/\{\{\-\-[\s\S]*?\-\-\}\}/', '', $content) ?? $content;
        self::validateBlockDirectives($content);
        $content = preg_replace('/@php\b/', '<?php', $content) ?? $content;
        $content = preg_replace('/@endphp\b/', '?>', $content) ?? $content;
        $content = preg_replace('/@csrf\b/', '<?php echo \\MicroPHP\\View::csrfField(); ?>', $content) ?? $content;

        foreach (['include', 'component'] as $directive) {
            $content = self::replaceDirective($content, $directive, static function (string $expression) use ($directive): string {
                $arguments = self::splitTopLevel($expression);
                if (count($arguments) < 1 || count($arguments) > 2 || trim($arguments[0]) === '') {
                    throw new RuntimeException("Malformed @{$directive} directive.");
                }
                $arguments[1] = $arguments[1] ?? '[]';
                $method = $directive === 'include' ? 'renderInclude' : 'renderComponent';
                return '<?php $__view_instance->' . $method . '(' . $arguments[0] . ', ' . $arguments[1] . '); ?>';
            });
        }

        $content = self::replaceDirective($content, 'class', static fn (string $e): string => '<?php echo \\MicroPHP\\View::classAttribute(' . $e . '); ?>');
        $content = self::replaceDirective($content, 'style', static fn (string $e): string => '<?php echo \\MicroPHP\\View::styleAttribute(' . $e . '); ?>');
        $content = self::replaceDirective($content, 'value', static fn (string $e): string => '<?php echo \\MicroPHP\\View::valueAttribute(' . $e . '); ?>');

        foreach (['disabled', 'readonly', 'checked', 'selected'] as $attribute) {
            $content = self::replaceDirective($content, $attribute, static fn (string $e): string => '<?php if (' . $e . ') echo "' . $attribute . '=\\"' . $attribute . '\\""; ?>');
        }

        $control = [
            'elseif' => static fn (string $e): string => '<?php elseif (' . $e . '): ?>',
            'if' => static fn (string $e): string => '<?php if (' . $e . '): ?>',
            'foreach' => static fn (string $e): string => '<?php foreach (' . $e . '): ?>',
            'for' => static fn (string $e): string => '<?php for (' . $e . '): ?>',
            'while' => static fn (string $e): string => '<?php while (' . $e . '): ?>',
            'isset' => static fn (string $e): string => '<?php if (isset(' . $e . ')): ?>',
            'continue' => static fn (string $e): string => '<?php if (' . $e . ') continue; ?>',
            'break' => static fn (string $e): string => '<?php if (' . $e . ') break; ?>',
        ];
        foreach ($control as $directive => $compiler) {
            $content = self::replaceDirective($content, $directive, $compiler);
        }

        $content = preg_replace_callback('/@use\(\s*([\'\"])([^\'\"]+)\1\s*\)/', static fn (array $m): string => '<?php use ' . $m[2] . '; ?>', $content) ?? $content;
        foreach (['endforeach' => 'endforeach', 'endfor' => 'endfor', 'endwhile' => 'endwhile'] as $directive => $statement) {
            $content = preg_replace('/@' . $directive . '\b/', '<?php ' . $statement . '; ?>', $content) ?? $content;
        }
        $content = preg_replace('/@(endisset|endif)\b/', '<?php endif; ?>', $content) ?? $content;
        $content = preg_replace('/@else\b/', '<?php else: ?>', $content) ?? $content;
        $content = preg_replace('/@break\b/', '<?php break; ?>', $content) ?? $content;
        $content = preg_replace('/@continue\b/', '<?php continue; ?>', $content) ?? $content;
        $content = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', static fn (array $m): string => '<?php echo ' . $m[1] . '; ?>', $content) ?? $content;
        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', static fn (array $m): string => '<?php echo \\MicroPHP\\View::escape(' . $m[1] . '); ?>', $content) ?? $content;

        if (preg_match('/@(if|elseif|foreach|for|while|isset|include|component|class|style|value)\s*\(/', $content, $match) === 1) {
            throw new RuntimeException('Malformed @' . $match[1] . ' directive.');
        }
        return $content;
    }

    private static function validateBlockDirectives(string $content): void
    {
        foreach (['if' => 'endif', 'foreach' => 'endforeach', 'for' => 'endfor', 'while' => 'endwhile', 'isset' => 'endisset'] as $open => $close) {
            preg_match_all('/@' . $open . '\s*\(/', $content, $openMatches);
            preg_match_all('/@' . $close . '\b/', $content, $closeMatches);
            if (count($openMatches[0]) !== count($closeMatches[0])) {
                throw new RuntimeException("Malformed @{$open} block.");
            }
        }
    }

    /** @param callable(string):string $compiler */
    private static function replaceDirective(string $content, string $name, callable $compiler): string
    {
        $offset = 0;
        $pattern = '/@' . preg_quote($name, '/') . '\s*\(/';
        while (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $matched = $match[0][0];
            $start = $match[0][1];
            $open = $start + strrpos($matched, '(');
            [$expression, $close] = self::balancedExpression($content, $open, $name);
            $replacement = $compiler(trim($expression));
            $content = substr($content, 0, $start) . $replacement . substr($content, $close + 1);
            $offset = $start + strlen($replacement);
        }
        return $content;
    }

    /** @return array{0:string,1:int} */
    private static function balancedExpression(string $content, int $open, string $name): array
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $stack = [')'];
        $quote = null;
        $escaped = false;
        for ($i = $open + 1, $length = strlen($content); $i < $length; $i++) {
            $char = $content[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) { $quote = null; }
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
            if (isset($pairs[$char])) { $stack[] = $pairs[$char]; continue; }
            if (in_array($char, [')', ']', '}'], true)) {
                if (array_pop($stack) !== $char) { throw new RuntimeException("Malformed @{$name} directive."); }
                if ($stack === []) { return [substr($content, $open + 1, $i - $open - 1), $i]; }
            }
        }
        throw new RuntimeException("Malformed @{$name} directive.");
    }

    /** @return string[] */
    private static function splitTopLevel(string $expression): array
    {
        $parts = [];
        $start = 0;
        $stack = [];
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $quote = null;
        $escaped = false;
        for ($i = 0, $length = strlen($expression); $i < $length; $i++) {
            $char = $expression[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) { $quote = null; }
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
            if (isset($pairs[$char])) { $stack[] = $pairs[$char]; continue; }
            if (in_array($char, [')', ']', '}'], true)) { array_pop($stack); continue; }
            if ($char === ',' && $stack === []) {
                $parts[] = trim(substr($expression, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($expression, $start));
        return $parts;
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function classAttribute(mixed $value): string
    {
        $classes = [];
        foreach ((array) $value as $key => $enabled) {
            if (is_int($key) && $enabled) { $classes[] = (string) $enabled; }
            elseif (!is_int($key) && $enabled) { $classes[] = (string) $key; }
        }
        $result = trim(implode(' ', $classes));
        return $result === '' ? '' : ' class="' . self::escape($result) . '"';
    }

    public static function styleAttribute(mixed $value): string
    {
        $styles = [];
        foreach ((array) $value as $key => $style) {
            if (is_int($key) && (is_string($style) || is_numeric($style)) && trim((string) $style) !== '') {
                $styles[] = rtrim((string) $style, ';');
            } elseif (!is_int($key) && (is_string($style) || is_numeric($style)) && (string) $style !== '') {
                $styles[] = rtrim((string) $key, ';') . ':' . $style;
            } elseif (!is_int($key) && $style) {
                $styles[] = rtrim((string) $key, ';');
            }
        }
        $result = trim(implode('; ', $styles));
        return $result === '' ? '' : ' style="' . self::escape(rtrim($result, '; ') . ';') . '"';
    }

    public static function valueAttribute(mixed $value): string { return 'value="' . self::escape($value) . '"'; }
    public static function csrfField(): string { return '<input type="hidden" name="_token" value="' . self::escape(self::csrfToken()) . '" />'; }
    public static function csrfToken(): string
    {
        return (function_exists('app') ? app(Security\Csrf::class) : new Security\Csrf())->token();
    }

    private static function viewsPath(): string { return self::$viewsPath ?? (defined('PAGES_PATH') ? PAGES_PATH : ROOT_PATH . '/app/pages'); }
    private static function cachePath(): string { return self::$cachePath ?? (defined('VIEW_CACHE_PATH') ? VIEW_CACHE_PATH : ROOT_PATH . '/var/cache/views'); }
    private static function componentsPath(): string
    {
        return self::$componentsPath ?? (defined('COMPONENTS_PATH') ? COMPONENTS_PATH : ROOT_PATH . '/app/components');
    }
    private static function cache(): ViewCache
    {
        return self::$cache ??= new ViewCache(self::cachePath(), defined('VIEW_CACHE_TRUST') && VIEW_CACHE_TRUST === true);
    }

    /** @return string[] */
    private static function allMicroFiles(): array
    {
        $files = [];
        foreach ([self::viewsPath(), self::componentsPath()] as $dir) {
            if (!is_dir($dir)) { continue; }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.micro.php')) { $files[] = $file->getPathname(); }
            }
        }
        return $files;
    }

    private static function viewToFile(string $view): string
    {
        if ($view === '' || str_contains($view, "\0") || str_contains($view, '\\') || str_contains($view, '..')) {
            throw new InvalidArgumentException('Invalid view name.');
        }
        return rtrim(self::viewsPath(), '/\\') . '/' . ltrim(str_replace('.', '/', $view), '/') . '.micro.php';
    }

    private static function isInsideTemplateRoots(string $file): bool
    {
        foreach ([self::viewsPath(), self::componentsPath()] as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) { continue; }
            $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR);
            if ($file === $realRoot || str_starts_with($file . DIRECTORY_SEPARATOR, $realRoot . DIRECTORY_SEPARATOR)) { return true; }
        }
        return false;
    }
}
