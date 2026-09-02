# MicroPHP modernization

This modernization keeps MicroPHP filesystem-first: plain PHP pages and
colocated components remain valid, and no controller or MVC layer is required.

## Architecture now in use

`Application` selects frontend, API, or controlled asset delivery.
`PageDispatcher` and `ApiDispatcher` are the canonical dispatch entry points;
the `Router` and static API registration surface remain compatibility facades.
`RouteResolver` only maps safe URL segments to real directories. Method
selection, inherited middleware execution, layouts, assets, views, responses,
and exception rendering are handled by `MethodResolver`, `MiddlewareResolver`,
`MiddlewarePipeline`, `LayoutRenderer`, `AssetManager`, `View`, `Response`, and
`ExceptionHandler` respectively.

API versions must be exact static children of `API_ROUTES_PATH`. Dynamic
matching starts only beneath that validated directory. Static routes take
precedence, ambiguous dynamic siblings throw `RoutingConfigurationException`,
and symlinks outside a route root are ignored before scanning. Legacy route
files are loaded only from the validated version tree.

## Security behavior

- Missing `.env` fails closed with production mode and debug output disabled.
- Unknown exceptions are logged and become generic HTTP 500 responses. API
  errors use `{ "error": { "code": "...", "message": "..." } }`; frontend
  errors use HTML. Only explicit `HttpException` values expose safe messages.
- Escaped template output and generated attributes use `ENT_QUOTES |
  ENT_SUBSTITUTE` with UTF-8. A balanced scanner handles nested directive
  expressions; `{!! ... !!}` remains the explicit raw-output form.
- `View::render()` and `renderFile()` are confined to page/component roots.
  `renderTrustedFile()` clearly names the trusted bypass.
- Frontend POST, PUT, PATCH, and DELETE requests require the session token from
  `_token` or `X-CSRF-Token`. `X-Requested-With` is never accepted as a token.
  Session CSRF is not automatically attached to bearer-token APIs.
- Component/page/layout assets belong to one request-scoped `AssetManager`.
  There is no component static queue or reset hook, making sequential
  persistent-worker requests isolated.

## HTTP API

`Request` is immutable and precomputes JSON state. Use `query()`, `post()`,
`json()`, `cookie()`/`cookies()`, `file()`/`files()`, `header()`, `route()`,
`routeParams()`, `method()`, and `path()`. `input()` intentionally excludes
route parameters; use deprecated `legacyInput()` only while migrating old
precedence assumptions.

`Response` validates status codes and headers, stores headers
case-insensitively, throws on JSON encoding failure, suppresses 204/304 bodies,
and supports body removal for HEAD. `Response::error()` creates standardized
API errors.

## Database

PDO defaults are exception mode, associative fetches, emulated prepares off,
and persistence off. Set `DB_PERSISTENT=true` only deliberately. Relative
SQLite paths resolve against `ROOT_PATH`, independent of the process working
directory.

`Database::transaction()` commits a successful callback, rolls back on every
throwable, and rethrows the original exception. Nested transactions are
explicitly rejected. Conditionless `update()`/`delete()` stay protected;
`updateAll()` and `deleteAll()` make intentional full-table work obvious.

## Migration notes

- Move `_guard.php` authorization into `_middleware.php`. Guards currently run
  through a deprecated middleware adapter, so there is one execution pipeline.
- Replace `Api::get()` and sibling static registrations with
  `app/api/<version>/<resource>/<METHOD>.php` handlers returning `Response`.
- Replace route/body ambiguity with explicit `$request->route('id')` and
  `$request->input('field')` calls.
- Point production web servers at `/public`; the root entry point remains a
  shared-hosting convenience only.

## Verification

`composer test` runs PHPUnit. The optional historical smoke script is available
as `composer test:smoke`. PHPUnit covers routing and traversal, API method
semantics, safe errors, templates, CSRF, assets, immutable HTTP values, and
transactions using a temporary SQLite database rather than
`database/library.db`. CI runs the suite on PHP 8.1, 8.2, 8.3, and 8.4.
