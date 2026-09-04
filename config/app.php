<?php
/**
 * MicroPHP application configuration.
 */

$envFile = ROOT_PATH . '/.env';
$env = is_file($envFile) ? (parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: []) : [];

// Environment settings.
// APP_ENV/APP_DEBUG gate error display (see ExceptionHandler) — keep
// APP_DEBUG off outside local development.
define('APP_ENV', $env['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));

$appUrl = rtrim((string) ($env['APP_URL'] ?? 'http://localhost:8000'), '/');
$parsedAppUrl = parse_url($appUrl);
if (
    filter_var($appUrl, FILTER_VALIDATE_URL) === false ||
    !is_array($parsedAppUrl) ||
    !in_array(strtolower((string) ($parsedAppUrl['scheme'] ?? '')), ['http', 'https'], true) ||
    empty($parsedAppUrl['host']) ||
    isset($parsedAppUrl['user']) ||
    isset($parsedAppUrl['pass']) ||
    isset($parsedAppUrl['query']) ||
    isset($parsedAppUrl['fragment'])
) {
    throw new RuntimeException('APP_URL must be an absolute http:// or https:// URL without credentials, a query, or a fragment.');
}
define('APP_URL', $appUrl);
$appUsesHttps = strtolower((string) $parsedAppUrl['scheme']) === 'https';

// Database settings.
define('DB_DRIVER', $env['DB_DRIVER'] ?? 'sqlite');
define('DB_DSN', $env['DB_DSN'] ?? null);

// For SQLite, you define the path to the database file.
// Make sure you have a 'database' folder in your project root.
define('DB_PATH', $env['DB_PATH'] ?? ROOT_PATH . '/database/library.db');

// These settings are ignored by SQLite but are available for other drivers.
// MariaDB uses the MySQL PDO driver. SQL Server uses pdo_sqlsrv. MongoDB uses
// ext-mongodb and can use DB_DSN for mongodb+srv:// or replica set URIs.
define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_PORT', $env['DB_PORT'] ?? null);
define('DB_NAME', $env['DB_NAME'] ?? 'microphp');
define('DB_USER', $env['DB_USER'] ?? null);
define('DB_PASS', $env['DB_PASS'] ?? null);
define('DB_AUTH_SOURCE', $env['DB_AUTH_SOURCE'] ?? null);
define('DB_PERSISTENT', filter_var($env['DB_PERSISTENT'] ?? false, FILTER_VALIDATE_BOOLEAN));

// Application settings.
define('API_SERVICE_ENABLED', filter_var($env['API_SERVICE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN));
define('PROJECT_NAME', $env['PROJECT_NAME'] ?? 'MicroPHP Application');

// Session cookies are secure by default when APP_URL uses HTTPS. SameSite=Lax
// preserves normal inbound navigation while adding defense in depth for CSRF.
$sessionSameSite = ucfirst(strtolower((string) ($env['SESSION_COOKIE_SAMESITE'] ?? 'Lax')));
if (!in_array($sessionSameSite, ['Lax', 'Strict', 'None'], true)) {
    throw new RuntimeException('SESSION_COOKIE_SAMESITE must be Lax, Strict, or None.');
}
$sessionSecureCookie = filter_var(
    $env['SESSION_COOKIE_SECURE'] ?? $appUsesHttps,
    FILTER_VALIDATE_BOOLEAN
);
if ($sessionSameSite === 'None' && !$sessionSecureCookie) {
    throw new RuntimeException('SESSION_COOKIE_SAMESITE=None requires SESSION_COOKIE_SECURE=true.');
}
define('SESSION_COOKIE_SECURE', $sessionSecureCookie);
define('SESSION_COOKIE_SAMESITE', $sessionSameSite);

// Browser response hardening. Applications can tune the CSP for their own
// external asset origins while retaining the other safe defaults.
define('SECURITY_HEADERS_ENABLED', filter_var($env['SECURITY_HEADERS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN));
define('HSTS_ENABLED', filter_var($env['HSTS_ENABLED'] ?? $appUsesHttps, FILTER_VALIDATE_BOOLEAN));
define(
    'CONTENT_SECURITY_POLICY',
    (string) ($env['CONTENT_SECURITY_POLICY']
        ?? "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net")
);

// Frontend page protection mode.
// guard: use inherited _guard.php files only.
// middleware: use inherited _middleware.php files only.
// both: run _guard.php checks first, then wrap page rendering in middleware.
define('PAGE_ACCESS_MODE', strtolower((string) ($env['PAGE_ACCESS_MODE'] ?? 'both')));

// Global middleware run by the routers before route/page-specific middleware.
// Entries may be MiddlewareInterface instances, callables, or class names that
// can be constructed by the application container.
define('FRONTEND_MIDDLEWARE', []);
define('API_CSRF_ENABLED', filter_var($env['API_CSRF_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN));
define('API_MIDDLEWARE', API_CSRF_ENABLED ? [\MicroPHP\Http\Middleware\CsrfMiddleware::class] : []);

// Application source tree. Mutable application code lives outside the public
// document root; framework internals stay in src/.
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Browser asset URLs. App-scoped assets stay colocated under app/ and are
// exposed through virtual /assets/application, /assets/pages, and
// /assets/components URLs. Standalone public/vendor files live in
// public/assets and use /assets URLs.
define('ASSETS_URL', '/assets');
define('APP_PUBLIC_URL', ASSETS_URL . '/application');
define('PAGES_PATH', APP_PATH . '/pages');
define('PAGES_URL', ASSETS_URL . '/pages');
define('LAYOUTS_PATH', APP_PATH . '/layouts');
define('API_ROUTES_PATH', APP_PATH . '/api');

define('APP_ASSETS_PATH', APP_PATH . '/assets');
define('APP_ASSETS_URL', APP_PUBLIC_URL);
define('PUBLIC_ASSETS_PATH', PUBLIC_PATH . '/assets');
define('PUBLIC_ASSETS_URL', ASSETS_URL);

// Component settings. Component class, template, CSS, and JS live together in
// app/components/<component-name>/.
define('COMPONENTS_PATH', APP_PATH . '/components');
define('COMPONENTS_URL', ASSETS_URL . '/components');

// Backward-compatible aliases used by older application code.
define('COMPONENT_ASSETS_PATH', COMPONENTS_PATH);
define('COMPONENT_ASSETS_URL', COMPONENTS_URL);

// Runtime cache.
define('VIEW_CACHE_PATH', ROOT_PATH . '/var/cache/views');

// View cache settings.
// When true, the .micro.php view engine trusts existing compiled cache files
// without comparing source modification times. Enable this only in production,
// and only when cache is warmed by `php bin/view-cache.php warm` during
// deployment. Otherwise template changes will not be visible until cache is
// cleared or warmed again. Disabled by default for development safety.
define('VIEW_CACHE_TRUST', filter_var($env['VIEW_CACHE_TRUST'] ?? false, FILTER_VALIDATE_BOOLEAN));
