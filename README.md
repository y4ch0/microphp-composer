# MicroPHP

MicroPHP is a small filesystem-driven PHP project skeleton. Pages live in
`app/pages`, API handlers live in `app/api`, reusable UI lives in
`app/components`, framework internals live in `src`, and the safe web document
root is `public`.

## Create a New Project

After this repository is published on Packagist:

```bash
composer create-project yacho/microphp my-app
cd my-app
php -S localhost:8000 -t public public/index.php
```

The `create-project` command runs `bin/setup-project.php`, which creates a
local `.env`, asks for the display name and database driver, and prepares the
runtime directories.

Run the setup wizard again at any time with:

```bash
composer run microphp:setup
```

Overwrite an existing `.env` and rerun the prompts with:

```bash
composer run microphp:setup -- --force
```

The setup wizard always requires an interactive terminal. Blank answers are
rejected, and optional empty values must be entered explicitly as `-`.

## Local Development

```bash
composer install
composer test
php -S localhost:8000 -t public public/index.php
```

The root `index.php` remains as a shared-hosting convenience entry point, but
production servers should point their document root to `public/`.

MicroPHP supports PHP 8.1 and newer. CI exercises PHP 8.1–8.4. If `.env` is
missing, `APP_ENV=production` and `APP_DEBUG=false` are assumed; the interactive
setup command explicitly creates development settings.

## HTTP and Filesystem Routing

`Request` exposes `method()`, `path()`, `query()`, `post()`, `json()`,
`cookie()`/`cookies()`, `file()`/`files()`, `header()`, and
`route()`/`routeParams()`. Route parameters are separate from submitted input;
`input()` checks POST, query, then JSON. `legacyInput()` retains the former route
parameter precedence during migration.

Pages remain simple `app/pages/**/index.php` or `index.micro.php` files. API
versions are static directories under `app/api`; resources beneath them may use
`[parameter]` directories and method files such as `GET.php` and `POST.php`.
Static directories win over dynamic ones, ambiguous dynamic siblings are a
configuration error, and resolved paths cannot escape their configured roots.

API method files return `Response` objects. Unsupported methods receive 405
with an accurate `Allow` header. HEAD uses an explicit handler or falls back to
GET without a body; OPTIONS may be explicit or generated automatically.

## Middleware, CSRF, and Errors

`_middleware.php` is inherited from root to leaf for pages and API resources.
Legacy `_guard.php` files are adapted into that middleware pipeline; migrate
their authorization callback to `_middleware.php`. Browser POST, PUT, PATCH,
and DELETE requests pass through CSRF middleware and must provide `_token` or
`X-CSRF-Token`. Bearer-token APIs do not receive session CSRF automatically.

Unexpected errors are logged with exception context. Production APIs receive a
stable JSON error object and frontend requests receive safe HTML; neither leaks
the original exception. Use `Response::error($message, $status, $code)` for
deliberate client-facing errors.

## Views, Assets, and Workers

`{{ ... }}` is escaped and `{!! ... !!}` is explicitly raw. Generated class,
style, value, CSRF, and metadata attributes use quote-safe UTF-8 escaping.
Template names and files are confined to configured page/component roots;
`renderTrustedFile()` is the explicit escape hatch for trusted application
files.

Every frontend dispatch owns a fresh `AssetManager`. Page, layout, application,
and component assets are deduplicated in deterministic order and translated
only from known filesystem roots, so component assets cannot leak between
persistent-worker requests.

## Database Safety

PDO uses exception mode, associative fetches, native prepares, and
non-persistent connections by default (`DB_PERSISTENT=false`). Relative SQLite
paths resolve beneath `ROOT_PATH`. `Database::transaction()` commits on success,
rolls back and rethrows on failure, and explicitly rejects nesting. Ordinary
update/delete calls require conditions; intentional full-table operations are
`Database::table('users')->updateAll(...)` and `deleteAll()`.

Tests create isolated temporary SQLite databases; they never write to the demo
`database/library.db`.

## Compatibility

`Router::dispatch()` and static `Api::get()`-style registration remain as
development-deprecated facades. New code should enter through `Application`,
`PageDispatcher`, filesystem API method files, and middleware.

## Common Commands

```bash
composer run microphp:make-component -- AlertBox
composer run microphp:make-api -- /api/v1/users/:id
composer run microphp:cache-clear
composer run microphp:cache-warm
```

## Publish Checklist

1. Commit source files, `composer.json`, `composer.lock`, `.env.example`, and
   docs.
2. Do not commit `vendor/`, `.env`, `var/cache`, `var/log`, or `var/sessions`.
3. Run `composer validate --strict`.
4. Run `composer dump-autoload --optimize --strict-psr`.
5. Run `composer test`.
6. Push a Git tag such as `v1.0.0`.
7. Submit the public repository URL to Packagist.
"# microphp-composer" 
