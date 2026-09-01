# MicroPHP modernization notes

What changed, why, and how to use it. Existing `_guard.php`, page/API file
conventions, and the view engine still work.

## 1. Query builder (`src/QueryBuilder.php`)

The main ask: a readable alternative to hand-typed SQL for common cases. The
canonical entry points live as static methods on `Database`.

```php
$admins = Database::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['role' => 'admin', 'active' => 1])
    ->orderBy('name')
    ->get();

$user = Database::table('users')->where(['id' => $id])->first();

Database::insert('posts', ['title' => $t, 'content' => $c, 'created_at' => date('c')]);
Database::update('posts', ['title' => $newTitle], ['id' => $id]);
Database::delete('posts', ['id' => $id]);
```

Everything still goes through PDO prepared statements. Table/column identifiers,
operators, join columns, and order directions are validated by the builder, and
empty `Database::update()` / `Database::delete()` condition arrays are rejected
to prevent accidental mass writes.

## 2. DI container (`src/Container.php`)

```php
$container = app(); // shared container, created on first use
$logger = app(MicroPHP\Logger::class);

class ReportGenerator {
    public function __construct(private MicroPHP\Logger $logger) {}
}

$generator = app()->make(ReportGenerator::class);
```

Only typed, non-builtin constructor parameters are autowired. Register services
in `bootstrap/app.php`'s `app()` function with `$container->bind()` or
`$container->singleton()`.

## 3. Request / Response (`src/Http/`)

`index.php` and `public/index.php` now capture one `MicroPHP\Http\Request` from
globals and pass it to `MicroPHP\Application`. The `/public` entry remains the
preferred production document root, while the root `index.php` is available for
local/shared-hosting convenience with the root `.htaccess` guard blocking direct
access to private directories. Routers dispatch to `MicroPHP\Http\Response`
objects and only send at the edge.

`Request::create()` is available for tests and internal dispatch. `Response`
includes `html()`, `text()`, `json()`, `noContent()`, and `redirect()` factories.

## 4. Middleware (`src/Http/MiddlewareInterface.php`, `MiddlewarePipeline.php`)

Middleware infrastructure is part of both core routers now. The frontend router
always dispatches through `MiddlewarePipeline`, and the API router uses the same
pipeline with CORS as its first built-in middleware.

```php
use MicroPHP\Http\MiddlewarePipeline;
use MicroPHP\Http\Middleware\LoggingMiddleware;
use MicroPHP\Http\Response;

$pipeline = (new MiddlewarePipeline())
    ->pipe(new LoggingMiddleware(app(MicroPHP\Logger::class)));

$response = $pipeline->handle($request, fn ($request) => Response::json(['ok' => true]));
$response->send();
```

Frontend page protection is configurable:

```ini
PAGE_ACCESS_MODE=guard       # inherited _guard.php files only
PAGE_ACCESS_MODE=middleware  # inherited _middleware.php files only
PAGE_ACCESS_MODE=both        # default: guards first, then middleware
```

Example page middleware:

```php
return auth_middleware([
    [
        'session_key' => 'user.role',
        'check' => ['Admin'],
        'on_fail' => '/login',
    ],
]);
```

API middleware can be registered globally, per API version with
`app/api/<version>/_middleware.php`, or per route:

```php
use MicroPHP\Api;
use MicroPHP\Database;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

Api::get('/posts', function (Request $request): array {
    return Database::table('posts')->get();
}, middleware: [
    fn (Request $request, callable $next): Response => $next($request)
        ->withHeader('X-Route', 'posts'),
]);
```

## 5. Unified components (`app/components/`)

Component PHP classes now live beside their templates and browser assets:

```text
app/components/button/Button.php
app/components/button/style.css
app/components/lorem/Lorem.php
app/components/lorem/view.micro.php
```

`View::component('button')` still resolves to `MicroPHP\Components\Button`.
`bootstrap/app.php` includes a component autoloader that maps that namespace to
the unified component folder convention, and `bin/create-component.php` now
generates the component class, template, stylesheet, and script together under
`app/components`.

## 6. Error handling (`src/Logger.php`, `src/ExceptionHandler.php`)

`index.php` used to hardcode `display_errors=1`, meaning stack traces were
always shown, in production too. Now:

- `APP_DEBUG` controls whether uncaught exceptions render a stack trace or a
  generic message.
- Every uncaught exception/error is logged to `var/log/app.log`.
- Swap `Logger` for Monolog later by binding `MicroPHP\Logger::class` to an
  adapter in `bootstrap/app.php`.

## 7. Enums (`src/Enums/`)

`DbDriver` and `HttpMethod` give the framework typed names for common values.
`Database` uses `DbDriver` in its driver switch, including MariaDB, SQL Server,
and MongoDB compatibility, and middleware/API code can use `HttpMethod` when
avoiding bare method strings is useful.

## 8. Bug fixes

- `config/app.php`: `DB_PATH` is no longer hardcoded over `.env`.
- `index.php`: `display_errors` is no longer forced on regardless of
  environment.
- `composer.json`: `App\` now maps to `app/`, Composer scripts are available
  for setup/cache/generators/tests, and `post-create-project-cmd` runs a starter
  setup wizard.
- `.env.example` and `README.md` are included for Packagist/create-project use.

## What's genuinely still missing for a 2026-shaped framework

Left out of this pass:

- **Attribute-based routing** (`#[Route(...)]`) as an alternative to the folder
  convention. This would need a route compiler and cache.
- **Route caching**: `Router` still walks the filesystem per request to resolve
  `[param]` segments.
- **Validation layer**: no typed request/DTO validation yet. `Request` now
  centralizes input reading, so a validator can build on top of it.
- **Worker-mode support**: component asset queues and the legacy global
  `$params` are still per-process state under persistent workers.
- **Console kernel**: `bin/*.php` scripts remain standalone entry points.
- **PHPUnit**: `tests/manual-smoke-test.php` remains the local verification
  stand-in until a fuller PHPUnit suite is added.
