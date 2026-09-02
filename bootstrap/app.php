<?php
/**
 * Bootstrap the MicroPHP application.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'MicroPHP\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = ROOT_PATH . '/src/' . $relative . '.php';

        if (is_file($file)) {
            require $file;
        }
    });

    require_once ROOT_PATH . '/src/Helpers.php';
}

require_once ROOT_PATH . '/config/app.php';

spl_autoload_register(function (string $class): void {
    $basePath = defined('APP_PATH') ? APP_PATH : ROOT_PATH . '/app';
    $prefixes = [
        'App\\' => $basePath,
        // Temporary compatibility for applications generated before the App\
        // namespace became the canonical starter-project namespace.
        'MicroPHP\\Application\\' => $basePath,
    ];

    foreach ($prefixes as $prefix => $path) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = rtrim($path, '/\\') . '/' . $relative . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});

$sessionPath = ROOT_PATH . '/var/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(function (string $class): void {
    $prefix = 'MicroPHP\\Components\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $segments = explode('\\', $relative);
    $className = array_pop($segments);
    $componentSegments = array_map(static function (string $segment): string {
        $segment = str_replace('_', '-', $segment);
        $segment = preg_replace('/(?<!^)[A-Z]/', '-$0', $segment) ?? $segment;

        return strtolower($segment);
    }, array_merge($segments, [$className]));

    $basePath = defined('COMPONENTS_PATH') ? COMPONENTS_PATH : ROOT_PATH . '/app/components';
    $file = rtrim($basePath, '/\\') . '/' . implode('/', $componentSegments) . '/' . $className . '.php';

    if (is_file($file)) {
        require $file;
    }
});

/**
 * Application service container.
 *
 * Bind additional services here (or from your own bootstrap hook) — anything
 * with typed constructor dependencies will be autowired by Container::make().
 * Database and Logger stay available through their existing static facades
 * too, so this is additive, not a breaking change.
 */
function app(?string $abstract = null): mixed
{
    static $container = null;

    if ($container === null) {
        $container = new MicroPHP\Container();

        $container->singleton(MicroPHP\Logger::class, static fn () => new MicroPHP\Logger(ROOT_PATH . '/var/log/app.log'));
        $container->singleton(MicroPHP\Database::class, static fn () => MicroPHP\Database::getInstance());
        $container->singleton(MicroPHP\Security\Csrf::class, static fn () => new MicroPHP\Security\Csrf());
    }

    return $abstract === null ? $container : $container->make($abstract);
}

// Route every uncaught exception/error through one place instead of relying
// on php.ini's display_errors (see src/ExceptionHandler.php).
(new MicroPHP\ExceptionHandler(app(MicroPHP\Logger::class), APP_DEBUG))->register();

return new MicroPHP\Application(app());
