<?php
/**
 * MicroPHP Framework
 * View and template engine.
 */

namespace MicroPHP;

class View
{
  private static ?string $viewsPath = null;
  private static ?string $cachePath = null;
  private static ?string $componentsPath = null;
  private static ?string $csrfSessionKey = '_microphp_csrf_token';
  private static ?ViewCache $cache = null;

  private static function viewsPath(): string
  {
    return self::$viewsPath ?? (defined('PAGES_PATH') ? PAGES_PATH : ROOT_PATH . '/app/pages');
  }

  private static function cachePath(): string
  {
    return self::$cachePath ?? (defined('VIEW_CACHE_PATH') ? VIEW_CACHE_PATH : ROOT_PATH . '/var/cache/views');
  }

  private static function componentsPath(): string
  {
    return self::$componentsPath ?? (defined('COMPONENTS_PATH')
        ? COMPONENTS_PATH
        : (defined('COMPONENT_ASSETS_PATH')
          ? COMPONENT_ASSETS_PATH
        : ROOT_PATH . '/app/components'));
  }

  private static function cache(): ViewCache
  {
    if (self::$cache === null) {
      $trust = defined('VIEW_CACHE_TRUST') && VIEW_CACHE_TRUST === true;
      self::$cache = new ViewCache(self::cachePath(), $trust);
    }
    return self::$cache;
  }

  /**
   * Override the default framework paths used by the view engine.
   *
   * @param string|null $viewsPath Absolute path to the pages directory.
   * @param string|null $cachePath Absolute path to the compiled view cache directory.
   * @param string|null $componentsPath Absolute path to the component asset/template directory.
   * @return void
   */
  public static function configurePaths(
    ?string $viewsPath = null,
    ?string $cachePath = null,
    ?string $componentsPath = null
  ): void {
    self::$viewsPath = $viewsPath;
    self::$cachePath = $cachePath;
    self::$componentsPath = $componentsPath;
    self::$cache = null;
  }

  /**
   * Render a named view.
   *
   * @param string $view Dotted view name relative to the pages directory.
   * @param array<string,mixed> $data Data exposed to the view.
   * @return string Rendered view output.
   */
  public static function render(string $view, array $data = [])
  {
    return self::renderFile(self::viewToFile($view), $data);
  }

  /**
   * Render a view file through the template cache.
   *
   * @param string $file Absolute path to a .micro.php template file.
   * @param array<string,mixed> $data Data exposed to the template.
   * @return string Rendered template output.
   */
  public static function renderFile(string $file, array $data = [])
  {
    if (!file_exists($file)) {
      throw new \Exception("View not found: {$file}");
    }

    $cacheFile = self::cache()->resolve($file, [self::class, 'compile']);

    // Expose data keys as template variables.
    extract($data, EXTR_SKIP);

    // Provide an instance for templates that expect view-level helpers.
    $__view_instance = new self();

    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }

    if (!defined('MICROPHP_VIEW_CONTEXT')) {
      define('MICROPHP_VIEW_CONTEXT', true);
    }

    // Buffer and include the compiled template.
    ob_start();
    try {
        include $cacheFile;
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return ob_get_clean();
  }

  /**
   * Echo a named view from inside another template.
   *
   * @param string $view Dotted view name relative to the pages directory.
   * @param array<string,mixed> $data Data exposed to the included view.
   * @return void
   */
  public static function include(string $view, array $data = [])
  {
    echo self::render($view, $data);
  }

  /**
   * Echo a class-based component resolved from a template component name.
   *
   * @param string $view Template component name, such as "button" or "forms.input".
   * @param array<string,mixed> $data Props passed to the component constructor.
   * @return void
   */
  public static function component(string $view, array $data = []) {
    $class = Component::resolveClass($view);
    if ($class === null) {
      throw new \RuntimeException("Component class not found: {$view}");
    }

    echo $class::renderComponent($data);
  }

  /**
   * Warm up the cache for all page and component templates.
   *
   * @return array{compiled: int, errors: array<string,string>}
   */
  public static function warmCache(): array
  {
    return self::cache()->warmAll(self::allMicroFiles(), [self::class, 'compile']);
  }

  /**
   * Clear compiled view cache files.
   *
   * @return int Number of deleted cache files.
   */
  public static function clearCache(): int
  {
    return self::cache()->clearAll();
  }

  /**
   * Return compiled view cache statistics.
   *
   * @return array{files: int, bytes: int, oldest: ?int, newest: ?int}
   */
  public static function cacheStats(): array
  {
    return self::cache()->stats();
  }

  /**
   * Compile template source into executable PHP.
   *
   * @param string $source Raw template source.
   * @return string Compiled PHP template source.
   */
  public static function compile(string $source): string
  {
    return self::compileString($source);
  }

  /**
   * Return all .micro.php files from the page and component template directories.
   *
   * @return string[] Absolute template file paths.
   */
  private static function allMicroFiles(): array
  {
    $files = [];
    foreach ([self::viewsPath(), self::componentsPath()] as $dir) {
      if (!is_dir($dir)) {
        continue;
      }
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.micro.php')) {
          $files[] = $fileInfo->getPathname();
        }
      }
    }
    return $files;
  }

  /**
   * Convert a dotted view name to a file path.
   *
   * @param string $view Dotted view name.
   * @return string Absolute view file path.
   */
  private static function viewToFile(string $view): string
  {
    $view = str_replace('.', '/', $view);
    $file = rtrim(self::viewsPath(), '/\\') . '/' . ltrim($view, '/\\') . '.micro.php';
    return $file;
  }

  /**
   * Compile template content by converting MicroPHP directives to PHP.
   *
   * @param string $content Raw template content.
   * @return string Compiled PHP template content.
   */
  private static function compileString(string $content): string
  {
    // 1) Remove blade-style comments {{-- comment --}}
    $content = preg_replace('/\{\{\-\-\s*([\s\S]*?)\s*\-\-\}\}/', '', $content);

    // 2) @php ... @endphp -> <?php ... >
    $content = preg_replace('/@php\b/', '<?php', $content);
    $content = preg_replace('/@endphp\b/', '?>', $content);

    // 3) @use("Some\Name\Space") -> use Some\Name\Space;
    $content = preg_replace_callback('/@use\(\s*[\'"](.+?)[\'"]\s*\)/', function($m){
      return '<?php use ' . $m[1] . '; ?>';
    }, $content);

    // 4) @continue(condition) and @break(condition)
    $content = preg_replace_callback('/@continue\(\s*(.*?)\s*\)/', function($m){
      return '<?php if('.$m[1].'){ continue; } ?>';
    }, $content);
    $content = preg_replace_callback('/@break\(\s*(.*?)\s*\)/', function($m){
      return '<?php if('.$m[1].'){ break; } ?>';
    }, $content);
    // Also allow bare @break and @continue without conditions.
    $content = preg_replace('/@break\b/', '<?php break; ?>', $content);
    $content = preg_replace('/@continue\b/', '<?php continue; ?>', $content);

    // 5) Handle @csrf -> hidden input.
    //
    // The token must be read at render time, not at compile time. Compiled
    // cache files are shared across requests, so embedding a literal token here
    // would leak the token generated during compilation to later visitors.
    $content = preg_replace(
      '/@csrf\b/',
      '<?php echo \'<input type="hidden" name="_token" value="\' . '
      . 'htmlspecialchars(\MicroPHP\View::csrfToken(), ENT_QUOTES, \'UTF-8\') . \'" />\'; ?>',
      $content
    );

    // 6) Blade-style echo {{ ... }} -> <?= htmlspecialchars(..., ENT_QUOTES, 'UTF-8') (escaped)
    $content = preg_replace_callback('/\{\!\!\s*(.+?)\s*\!\!\}/s', function($m){
      return '<?php echo ' . $m[1] . '; ?>';
    }, $content);
    $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function($m){
      return '<?php echo htmlspecialchars(' . $m[1] . ', ENT_QUOTES, \'UTF-8\'); ?>';
    }, $content);

    // 7) @class([...]) -> output class=""
    $content = preg_replace_callback('/@class\(\s*(\[(?:.|\s)*?\])\s*\)/s', function($m) {
        $arrayPhp = $m[1];
        $php = '<?php ' .
            '$__class_arr = ' . $arrayPhp . ';' .
            'if (is_array($__class_arr)) {' .
                '$__class_result = [];' .
                'foreach ($__class_arr as $k => $v) {' .
                    'if (is_int($k)) { if ($v) $__class_result[] = $v; }' .
                    'else { if ($v) $__class_result[] = $k; }' .
                '}' .
                '$__class_final = trim(implode(" ", array_filter($__class_result)));' .
            '} else { $__class_final = (string)$__class_arr; }' .
            'if (!empty($__class_final)) { echo " class=\"" . htmlspecialchars($__class_final, ENT_QUOTES, "UTF-8") . "\""; } ?>';
        return $php;
    }, $content);

    // 8) @style([...]) -> output style=""
    $content = preg_replace_callback('/@style\(\s*(\[(?:.|\s)*?\])\s*\)/s', function($m) {
        $arrayPhp = $m[1];
        $php = '<?php ' .
            '$__style_arr = ' . $arrayPhp . ';' .
            'if (is_array($__style_arr)) {' .
                '$__style_result = [];' .
                'foreach ($__style_arr as $__key => $__value) {' .
                    'if (!is_string($__value) && !is_numeric($__value)) {' .
                        'if ($__value) { $__style_result[] = rtrim((string)$__key, ";"); }' .
                    '} elseif (is_numeric($__key)) {' .
                        'if (is_string($__value) && trim($__value) !== "") { $__style_result[] = rtrim($__value, ";"); }' .
                    '} else {' .
                        'if ((is_string($__value) || is_numeric($__value)) && (string)$__value !== "") { $__style_result[] = $__key . ":" . $__value; }' .
                    '}' .
                '}' .
                '$__style_final = implode("; ", array_filter($__style_result));' .
            '} else { $__style_final = (string)$__style_arr; }' .
            'if (!empty($__style_final)) { echo " style=\"" . trim(rtrim($__style_final, "; ")) . ";\""; } ?>';
        return $php;
    }, $content);


    // 9) Attributes shortcuts:
    $content = preg_replace_callback(
      '/@value\(\s*("[^"]*"|\$[a-zA-Z_][a-zA-Z0-9_]*|[a-zA-Z_][a-zA-Z0-9_]*\([^)]*\))\s*\)/',
      function($matches) {
        return '<?php echo \'value="\' . htmlspecialchars(' . $matches[1] . ') . \'"\' ?>';
      },
      $content
    );

    // - value="{{ $var }}" already handled by {{ }} -> escaped
    // - boolean attributes: disabled, readonly when used like: disabled="@someCondition" or simply disabled
    // We'll provide helper syntax: disabled="@condition" -> <?php if(condition) echo "disabled";
    $content = preg_replace_callback('/\b(disabled|readonly|checked|selected)\s*=\s*"(?:\s*)@([^"]+)"/', function($m){
      $attr = $m[1];
      $cond = $m[2];
      return '<?php if('.$cond.'): echo "'.$attr.'=\"'.$attr.'\""; endif; ?>';
    }, $content);

    // Also handle disabled="@condition" without quotes: disabled=@condition
    $content = preg_replace_callback('/\b(disabled|readonly|checked|selected)\s*=\s*@([^\s>]+)/', function($m){
      $attr = $m[1];
      $cond = $m[2];
      return '<?php if('.$cond.'): echo "'.$attr.'=\"'.$attr.'\""; endif; ?>';
    }, $content);

    // Also allow boolean attributes written as simply: @disabled($cond) -> prints disabled when true
    $content = preg_replace_callback('/@disabled\(\s*(.*?)\s*\)/', function($m){
      return '<?php if('.$m[1].'): echo "disabled=\"disabled\""; endif; ?>';
    }, $content);
    $content = preg_replace_callback('/@readonly\(\s*(.*?)\s*\)/', function($m){
      return '<?php if('.$m[1].'): echo "readonly=\"readonly\""; endif; ?>';
    }, $content);
    $content = preg_replace_callback('/@checked\(\s*(.*?)\s*\)/', function($m){
      return '<?php if('.$m[1].'): echo "checked=\"checked\""; endif; ?>';
    }, $content);
    $content = preg_replace_callback('/@selected\(\s*(.*?)\s*\)/', function($m){
      return '<?php if('.$m[1].'): echo "selected=\"selected\""; endif; ?>';
    }, $content);

    $content = preg_replace_callback('/@isset\s*\((.*?)\)/', function($m){
      return '<?php if('.$m[1].'): ?>';
    }, $content);

    $content = preg_replace_callback('/@endisset/', function($m){
      return '<?php endif; ?>';
    }, $content);


    // 10) @include("view", ['a'=>1]) and class-based @component("name", ['a'=>1]).
    // Compile both into PHP calls; if the second argument is missing, pass [].
    $content = preg_replace_callback(
      '/@include\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/s',
      function($m){
        $view = $m[1];
        $arg = isset($m[2]) && trim($m[2]) !== '' ? $m[2] : '[]';
        return '<?php \MicroPHP\View::include("'.$view.'", '.$arg.'); ?>';
      },
      $content
    );
    $content = preg_replace_callback(
      '/@component\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/s',
      function($m){
        $view = $m[1];
        $arg = isset($m[2]) && trim($m[2]) !== '' ? $m[2] : '[]';
        return '<?php \MicroPHP\View::component("'.$view.'", '.$arg.'); ?>';
      },
      $content
    );

    // 11) Simple control structures/@directives commonly expected:
    // @if (...) @endif, @elseif, @else
    $content = preg_replace('/@if\s*\((.*?)\)/', '<?php if($1): ?>', $content);
    $content = preg_replace('/@elseif\s*\((.*?)\)/', '<?php elseif($1): ?>', $content);
    $content = preg_replace('/@else\b/', '<?php else: ?>', $content);
    $content = preg_replace('/@endif\b/', '<?php endif; ?>', $content);

    // @foreach/@endforeach, @for/@endfor, @while/@endwhile
    $content = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach($1): ?>', $content);
    $content = preg_replace('/@endforeach\b/', '<?php endforeach; ?>', $content);
    $content = preg_replace('/@for\s*\((.*?)\)/', '<?php for($1): ?>', $content);
    $content = preg_replace('/@endfor\b/', '<?php endfor; ?>', $content);
    $content = preg_replace('/@while\s*\((.*?)\)/', '<?php while($1): ?>', $content);
    $content = preg_replace('/@endwhile\b/', '<?php endwhile; ?>', $content);

    // 12) Raw PHP echo shorthand for <?= ... is allowed through {!! ... !!} earlier

    return $content;
  }

  /**
   * Ensure the CSRF token exists in the current session.
   *
   * @return void
   */
  private static function ensureCsrfToken()
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }
    if (empty($_SESSION[self::$csrfSessionKey])) {
      $_SESSION[self::$csrfSessionKey] = bin2hex(random_bytes(32));
    }
  }

  /**
   * Get the CSRF token for the current session.
   *
   * @return string Current CSRF token.
   */
  public static function csrfToken(): string
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }
    self::ensureCsrfToken();
    return $_SESSION[self::$csrfSessionKey];
  }
}
