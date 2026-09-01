#!/usr/bin/env php
<?php
/**
 * CLI tool for generating MicroPHP class-based components.
 *
 * Each generated component lives in one folder:
 *   app/components/<component-name>/<ClassName>.php
 *   app/components/<component-name>/view.micro.php
 *   app/components/<component-name>/style.css
 *   app/components/<component-name>/script.js
 *
 * Usage:
 *   php bin/create-component.php AlertBox
 *   php bin/create-component.php Forms/Input
 *   php bin/create-component.php theme-change --force
 *   php bin/create-component.php AlertBox --dry-run
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require_once dirname(__DIR__) . '/bootstrap/app.php';

$arguments = array_values(array_filter(array_slice($argv, 1), static fn(string $arg): bool => $arg !== ''));
$force = in_array('--force', $arguments, true);
$dryRun = in_array('--dry-run', $arguments, true);
$name = firstPositionalArgument($arguments);

if ($name === null || in_array($name, ['-h', '--help', 'help'], true)) {
    usage(0);
}

try {
    $component = normalizeComponentName($name);
    $componentDir = rtrim(COMPONENTS_PATH, '/\\') . '/' . $component['assetName'];
    $classPath = $componentDir . '/' . $component['className'] . '.php';

    if (!$dryRun) {
        ensureDirectory($componentDir);
    }

    $created = [];
    writeGeneratedFile($classPath, componentClassSource($component), $force, $dryRun, $created);
    writeGeneratedFile($componentDir . '/view.micro.php', componentViewSource($component), $force, $dryRun, $created);
    writeGeneratedFile($componentDir . '/style.css', componentStyleSource($component), $force, $dryRun, $created);
    writeGeneratedFile($componentDir . '/script.js', componentScriptSource($component), $force, $dryRun, $created);

    echo ($dryRun ? 'Component would be created: ' : 'Component created: ') . "{$component['fqcn']}\n";
    echo "Template usage: @component(\"{$component['templateName']}\")\n";
    echo $dryRun ? "Files to create:\n" : "Files:\n";
    foreach ($created as $file) {
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

function usage(int $exitCode): never
{
    echo "Usage: php bin/create-component.php <ComponentName> [--force] [--dry-run]\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php bin/create-component.php AlertBox\n";
    echo "  php bin/create-component.php Forms/Input\n";
    echo "  php bin/create-component.php theme-change\n";
    exit($exitCode);
}

/**
 * @return array{
 *   classSegments: string[],
 *   namespace: string,
 *   className: string,
 *   fqcn: string,
 *   assetName: string,
 *   cssClass: string,
 *   displayName: string,
 *   templateName: string
 * }
 */
function normalizeComponentName(string $name): array
{
    $name = trim($name);
    $name = preg_replace('/^\\\\?MicroPHP\\\\Components\\\\?/', '', $name) ?? $name;
    $name = str_replace(['\\', '.'], '/', $name);
    $name = trim($name, "/ \t\n\r\0\x0B");

    if ($name === '') {
        throw new RuntimeException('Component name cannot be empty.');
    }

    $rawSegments = array_values(array_filter(explode('/', $name), static fn(string $segment): bool => $segment !== ''));
    $classSegments = [];

    foreach ($rawSegments as $segment) {
        if (str_contains($segment, '..')) {
            throw new RuntimeException('Component name cannot contain "..".');
        }

        $classSegments[] = studlySegment($segment);
    }

    $className = $classSegments[count($classSegments) - 1];
    $namespaceSegments = array_slice($classSegments, 0, -1);
    $namespace = 'MicroPHP\\Components' . ($namespaceSegments ? '\\' . implode('\\', $namespaceSegments) : '');
    $assetSegments = array_map('kebabSegment', $classSegments);
    $assetName = implode('/', $assetSegments);

    return [
        'classSegments' => $classSegments,
        'namespace' => $namespace,
        'className' => $className,
        'fqcn' => $namespace . '\\' . $className,
        'assetName' => $assetName,
        'cssClass' => str_replace('/', '-', $assetName) . '-component',
        'displayName' => trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $className) ?? $className),
        'templateName' => implode('.', $assetSegments),
    ];
}

function studlySegment(string $segment): string
{
    $words = preg_split('/[-_\s]+/', trim($segment));
    $studly = '';

    foreach ($words ?: [] as $word) {
        if ($word === '') {
            continue;
        }
        $studly .= ucfirst($word);
    }

    if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly)) {
        throw new RuntimeException("Invalid component segment: {$segment}");
    }

    return $studly;
}

function kebabSegment(string $segment): string
{
    $segment = str_replace('_', '-', $segment);
    $segment = preg_replace('/(?<!^)[A-Z]/', '-$0', $segment) ?? $segment;

    return strtolower($segment);
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
 * @param array<string,mixed> $component
 */
function componentClassSource(array $component): string
{
    $source = <<<'PHP'
<?php
namespace {{ namespace }};

use MicroPHP\Component;

class {{ className }} extends Component
{
  /**
   * Create the {{ displayName }} component.
   */
  public function __construct(
    protected string $title = '{{ displayName }}',
    protected string $description = 'Generated MicroPHP component.',
  ) {}

  /**
   * Render the {{ displayName }} component.
   *
   * @return string Rendered component markup.
   */
  public function render(): string
  {
    return $this->view('view.micro.php', [
      'cssClass' => '{{ cssClass }}',
      'title' => $this->title,
      'description' => $this->description,
    ]);
  }
}

PHP;

    return replacePlaceholders($source, $component);
}

/**
 * @param array<string,mixed> $component
 */
function componentViewSource(array $component): string
{
    $source = <<<'HTML'
<section class="{{ $cssClass }}" data-component="{{ $cssClass }}">
    <h2>{{ $title }}</h2>
    <p>{{ $description }}</p>
</section>

HTML;

    return $source;
}

/**
 * @param array<string,mixed> $component
 */
function componentStyleSource(array $component): string
{
    return <<<CSS
.{$component['cssClass']} {
  display: block;
  padding: 1rem;
  border: 1px solid #d8dee4;
  border-radius: 6px;
  background: #ffffff;
}

.{$component['cssClass']} h2 {
  margin: 0 0 0.35rem;
  font-size: 1.25rem;
}

.{$component['cssClass']} p {
  margin: 0;
}

CSS;
}

/**
 * @param array<string,mixed> $component
 */
function componentScriptSource(array $component): string
{
    return <<<JS
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-component='{$component['cssClass']}']").forEach((component) => {
        component.dataset.ready = "true";
    });
});

JS;
}

/**
 * @param array<string,mixed> $values
 */
function replacePlaceholders(string $source, array $values): string
{
    foreach ($values as $key => $value) {
        if (is_string($value)) {
            $source = str_replace('{{ ' . $key . ' }}', $value, $source);
        }
    }

    return $source;
}
