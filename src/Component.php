<?php
/**
 * MicroPHP Framework
 * Base class for class-based components.
 *
 * Design assumptions:
 *  - Props are ordinary typed constructor parameters, usually promoted
 *    properties, instead of variables created through extract($data).
 *    A typo in a prop name throws an exception instead of becoming an
 *    undefined variable at render time.
 *  - A component is an ordinary PHP class, so IDE completion, static analysis,
 *    and "go to definition" work like they do for any other class.
 *  - Component classes, templates, styles, and scripts live together under
 *    app/components/<component-name>/ by default.
 *  - Components live under MicroPHP\Components\, so their names cannot collide
 *    with framework classes such as MicroPHP\View.
 */

namespace MicroPHP;

abstract class Component
{
  private ?AssetManager $assetManager = null;

  /** Override when the asset directory name cannot be inferred from the class name. */
  protected static ?string $assetName = null;

  /**
   * Render the component and return its HTML.
   *
   * @return string Rendered component markup.
   */
  abstract public function render(): string;

  /**
   * Build a component instance from an associative props array.
   *
   * @param array<string,mixed> $props Props passed to the component constructor as named arguments.
   * @return static The constructed component instance.
   */
  public static function make(array $props = []): static
  {
    try {
      return new static(...$props);
    } catch (\Throwable $e) {
      throw new \RuntimeException(
        'Unable to build component ' . static::class . ': check the passed props (' . $e->getMessage() . ')',
        previous: $e
      );
    }
  }

  /**
   * Render the component and queue its scoped assets once per request.
   *
   * @param array<string,mixed> $props Props passed to the component constructor.
   * @return string Rendered component markup.
   */
  final public static function renderComponent(array $props = [], ?AssetManager $assets = null): string
  {
    $instance = static::make($props);
    $instance->assetManager = $assets;
    $assets?->registerComponentDirectory(static::assetDirectory());
    return $instance->render();
  }

  /**
   * Render a .micro.php template located in the component directory.
   *
   * @param string $relativeTemplate Template path relative to the component directory.
   * @param array<string,mixed> $data Data exposed to the component template.
   * @return string Rendered template markup.
   */
  protected function view(string $relativeTemplate, array $data = []): string
  {
    $file = static::assetDirectory() . '/' . ltrim($relativeTemplate, '/\\');

    if (!is_file($file)) {
      throw new \RuntimeException(static::class . ": template not found: {$file}");
    }

    return (new View($this->assetManager))->renderTemplateFile($file, $data);
  }

  protected function component(string $name, array $props = []): string
  {
    $class = self::resolveClass($name);
    if ($class === null) {
      throw new \RuntimeException("Component class not found: {$name}");
    }
    return $class::renderComponent($props, $this->assetManager);
  }

  /**
   * Return the component asset name used under the configured component asset root.
   *
   * @return string Component asset name, such as "button", "theme-change", or "forms/input".
   */
  protected static function assetName(): string
  {
    if (static::$assetName !== null && trim(static::$assetName, '/\\') !== '') {
      return trim(str_replace('\\', '/', static::$assetName), '/');
    }

    $prefix = 'MicroPHP\\Components\\';
    $relativeClass = str_starts_with(static::class, $prefix)
      ? substr(static::class, strlen($prefix))
      : (new \ReflectionClass(static::class))->getShortName();

    $segments = explode('\\', $relativeClass);
    return implode('/', array_map([self::class, 'kebabCase'], $segments));
  }

  /**
   * Return the absolute directory that stores templates and assets for the component.
   *
   * @return string Absolute component directory.
   */
  protected static function assetDirectory(): string
  {
    return rtrim(self::componentAssetsPath(), '/\\') . '/' . static::assetName();
  }


  /**
   * Return the absolute root directory for component files.
   *
   * @return string Absolute component root directory.
   */
  private static function componentAssetsPath(): string
  {
    if (defined('COMPONENTS_PATH')) {
      return COMPONENTS_PATH;
    }

    return defined('COMPONENT_ASSETS_PATH')
      ? COMPONENT_ASSETS_PATH
      : ROOT_PATH . '/app/components';
  }

  /**
   * Return the public URL root for component assets.
   *
   * @return string Public component asset URL root.
   */
  private static function componentAssetsUrl(): string
  {
    if (defined('COMPONENTS_URL')) {
      return COMPONENTS_URL;
    }

    return defined('COMPONENT_ASSETS_URL')
      ? COMPONENT_ASSETS_URL
      : '/assets/components';
  }

  /**
   * Convert a StudlyCase class segment to a kebab-case asset segment.
   *
   * @param string $value Class-name segment.
   * @return string Kebab-case segment.
   */
  private static function kebabCase(string $value): string
  {
    $value = str_replace('_', '-', $value);
    $value = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;
    return strtolower($value);
  }

  /**
   * Resolve a template component name to a class under MicroPHP\Components\.
   *
   * @param string $name Template component name, such as "button", "theme-change", or "forms.input".
   * @return class-string<Component>|null Resolved component class or null when no class exists.
   */
  public static function resolveClass(string $name): ?string
  {
    $segments = preg_split('/[.\/]+/', trim($name, '/.'));
    $studly = array_map(
      fn(string $s) => str_replace(['-', '_'], '', ucwords($s, '-_')),
      $segments
    );

    $class = 'MicroPHP\\Components\\' . implode('\\', $studly);

    if (!class_exists($class)) {
      $classFile = static::componentClassFile($studly);
      if (is_file($classFile)) {
        require_once $classFile;
      }
    }

    return (class_exists($class) && is_subclass_of($class, self::class))
      ? $class
      : null;
  }

  /**
   * @param string[] $classSegments
   */
  private static function componentClassFile(array $classSegments): string
  {
    $className = $classSegments[count($classSegments) - 1] ?? '';
    $assetName = implode('/', array_map([self::class, 'kebabCase'], $classSegments));

    return rtrim(self::componentAssetsPath(), '/\\') . '/' . $assetName . '/' . $className . '.php';
  }
}
