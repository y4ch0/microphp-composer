<?php
/**
 * MicroPHP Framework
 * Hand-rolled smoke test for Container, QueryBuilder, and Database's
 * insert/update/delete methods.
 *
 * NOTE: PHPUnit isn't wired in here — installing it needs Composer to reach
 * packagist.org, which wasn't reachable while building this. Add it with
 * `composer require --dev phpunit/phpunit` when you have normal network
 * access, then port these checks into real test cases. Until then, this
 * script gives you a fast way to verify the framework still behaves after
 * changes: `php tests/manual-smoke-test.php`.
 *
 * Database checks use a disposable SQLite file created for this run. The demo
 * database/library.db is never opened by this script.
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/bootstrap/app.php';

use MicroPHP\Application;
use MicroPHP\Container;
use MicroPHP\Api;
use MicroPHP\Database;
use MicroPHP\Enums\DbDriver;
use MicroPHP\Http\MiddlewarePipeline;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Routing\MethodResolver;
use MicroPHP\Routing\RouteResolver;
use MicroPHP\Routing\RoutingConfigurationException;
use MicroPHP\Router;

$failures = 0;

function check(string $label, bool $condition): void
{
    global $failures;
    if ($condition) {
        echo "  [ok] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
        $failures++;
    }
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

echo "== Container ==\n";
class TestLoggerConsumer
{
    public function __construct(public MicroPHP\Logger $logger)
    {
    }
}
$container = new Container();
$container->singleton(MicroPHP\Logger::class, fn () => new MicroPHP\Logger(ROOT_PATH . '/var/log/test.log'));
$consumer = $container->make(TestLoggerConsumer::class);
check('autowires a typed constructor dependency', $consumer->logger instanceof MicroPHP\Logger);
$again = $container->make(MicroPHP\Logger::class);
check('singleton returns the same instance on repeat resolution', $consumer->logger === $again);

echo "== Http\\Request ==\n";
$_SERVER['REQUEST_URI'] = '/posts/42?sort=new';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['title' => 'from post'];
$_GET = ['sort' => 'new']; // the real SAPI populates this from REQUEST_URI; CLI needs it set manually
$request = Request::fromGlobals();
check('parses path without query string', $request->path() === 'posts/42');
check('parses method', $request->method() === 'POST');
check('input() reads from POST', $request->input('title') === 'from post');
check('input() reads from query when not in POST', $request->input('sort') === 'new');
$routedRequest = $request->withRouteParams(['id' => '42']);
$copiedRouteParams = $routedRequest->routeParams();
$copiedRouteParams['id'] = 'changed';
check('route() reads route params', $routedRequest->route('id') === '42');
check('routeParams() returns a copy', $routedRequest->route('id') === '42');

echo "== Routing\\RouteResolver ==\n";
$fixture = ROOT_PATH . '/var/test-route-resolver';
remove_tree($fixture);
mkdir($fixture . '/home', 0777, true);
mkdir($fixture . '/users/new', 0777, true);
mkdir($fixture . '/users/[id]/posts/[postId]', 0777, true);
file_put_contents($fixture . '/users/new/GET.php', '<?php return true;');

$resolver = new RouteResolver();
$homeMatch = $resolver->resolve($fixture, '/', ['home']);
$staticMatch = $resolver->resolve($fixture, '/users/new');
$dynamicMatch = $resolver->resolve($fixture, '/users/42/posts/99');
check('resolves default route segments', $homeMatch !== null && basename($homeMatch->directory) === 'home');
check('prefers static routes before dynamic routes', $staticMatch !== null && basename($staticMatch->directory) === 'new' && $staticMatch->params === []);
check('captures multiple dynamic route params', $dynamicMatch !== null && $dynamicMatch->params === ['id' => '42', 'postId' => '99']);
check('rejects plain traversal attempts', $resolver->resolve($fixture, '/../config') === null);
check('rejects encoded traversal attempts', $resolver->resolve($fixture, '/%2e%2e/config') === null);
check('rejects encoded slash attempts', $resolver->resolve($fixture, '/users/a%2Fb') === null);

mkdir($fixture . '/ambiguous/[first]', 0777, true);
mkdir($fixture . '/ambiguous/[second]', 0777, true);
$ambiguousRejected = false;
try {
    $resolver->resolve($fixture, '/ambiguous/value');
} catch (RoutingConfigurationException) {
    $ambiguousRejected = true;
}
check('rejects ambiguous dynamic route directories', $ambiguousRejected);

$methodResolver = new MethodResolver();
check('method resolver whitelists method filenames', $methodResolver->resolve($fixture . '/users/new', 'GET') !== null);
check('method resolver rejects arbitrary method filenames', $methodResolver->resolve($fixture . '/users/new', 'TRACE') === null);
remove_tree($fixture);

echo "== Http\\Middleware ==\n";
$events = [];
$pipeline = (new MiddlewarePipeline())->pipe(
    function (Request $request, callable $next) use (&$events): Response {
        $events[] = 'before';
        $response = $next($request);
        $events[] = 'after';

        return $response->withHeader('X-Smoke-Test', 'middleware');
    }
);
$response = $pipeline->handle($request, function (Request $request): Response {
    return Response::html('middleware ok');
});
check('callable middleware wraps the destination handler', $events === ['before', 'after']);
check('middleware can decorate the response', ($response->headers()['X-Smoke-Test'] ?? null) === 'middleware');

$blocked = (new MiddlewarePipeline())
    ->pipe(fn (Request $request, callable $next): Response => Response::html('blocked', 403))
    ->handle($request, fn (Request $request): Response => Response::html('should not run'));
check('middleware can stop the request before the destination', $blocked->status() === 403 && $blocked->body() === 'blocked');

echo "== Api Router Middleware ==\n";
Api::get(
    '/smoke/:id',
    function (Request $request): Response {
        return Response::json([
            'id' => $request->route('id'),
            'source' => 'request-object',
        ]);
    },
    ['allowed_origins' => ['https://example.test']],
    function (Request $request, callable $next): Response {
        return $next($request)->withHeader('X-Api-Route-Middleware', 'yes');
    }
);
$apiResponse = (new Api())->dispatch(Request::create(
    method: 'GET',
    path: '/api/v1/smoke/42',
    headers: ['Origin' => 'https://example.test']
));
$apiPayload = json_decode($apiResponse->body(), true);
check('API router passes route params through Request', ($apiPayload['id'] ?? null) === '42');
check('API route middleware decorates the response', ($apiResponse->headers()['X-Api-Route-Middleware'] ?? null) === 'yes');
check('API CORS middleware decorates matched responses', ($apiResponse->headers()['Access-Control-Allow-Origin'] ?? null) === 'https://example.test');

$internalPayload = Api::makeRequest('GET', '/api/v1/smoke/77');
check('internal API calls accept /api-prefixed URIs', ($internalPayload['id'] ?? null) === '77');

$preflight = (new Api())->dispatch(Request::create(
    method: 'OPTIONS',
    path: '/api/v1/smoke/42',
    headers: ['Origin' => 'https://example.test']
));
check('API OPTIONS returns no body', $preflight->status() === 204 && $preflight->body() === '');
check('API OPTIONS includes an Allow header', ($preflight->headers()['Allow'] ?? null) === 'GET, HEAD, OPTIONS');

echo "== Filesystem API Routes ==\n";
$fsStatus = (new Api())->dispatch(Request::create('GET', '/api/v1/status'));
$fsStatusPayload = json_decode($fsStatus->body(), true);
check('filesystem API GET.php returns a Response', $fsStatus->status() === 200 && ($fsStatusPayload['status'] ?? null) === 'ok');

$fsDynamic = (new Api())->dispatch(Request::create('GET', '/api/v1/status/abc'));
$fsDynamicPayload = json_decode($fsDynamic->body(), true);
check('filesystem API route params use Request::route()', ($fsDynamicPayload['id'] ?? null) === 'abc');

$fsMethodNotAllowed = (new Api())->dispatch(Request::create(
    'POST',
    '/api/v1/status',
    headers: ['X-CSRF-Token' => app(\MicroPHP\Security\Csrf::class)->token()]
));
check('filesystem API returns 405 for known route with unsupported method', $fsMethodNotAllowed->status() === 405);
check('filesystem API 405 includes Allow header', ($fsMethodNotAllowed->headers()['Allow'] ?? null) === 'GET, HEAD, OPTIONS');

$fsHead = (new Api())->dispatch(Request::create('HEAD', '/api/v1/status'));
check('filesystem API HEAD falls back to GET without a body', $fsHead->status() === 200 && $fsHead->body() === '');

$fsOptions = (new Api())->dispatch(Request::create('OPTIONS', '/api/v1/status'));
check('filesystem API OPTIONS is generated centrally', $fsOptions->status() === 204 && ($fsOptions->headers()['Allow'] ?? null) === 'GET, HEAD, OPTIONS');

$traversalPaths = [
    '/api/../pages',
    '/api/../api/v1/status',
    '/api/%2e%2e/pages',
    '/api/%252e%252e/pages',
    '/api/..\\pages',
    '/api/%5c..%5cpages',
    '/api/v1/../../config',
    '/api/v1/%2e%2e/status',
];
$allTraversalRejected = true;
foreach ($traversalPaths as $traversalPath) {
    $traversalResponse = (new Api())->dispatch(Request::create('GET', $traversalPath));
    $allTraversalRejected = $allTraversalRejected && in_array($traversalResponse->status(), [400, 404], true);
}
check('API rejects plain, encoded, repeated, slash, and backslash traversal', $allTraversalRejected);

echo "== API Generator ==\n";
$generatedRouteName = '__generator_smoke_' . str_replace('.', '_', uniqid('', true));
$generatedRouteRoot = rtrim(API_ROUTES_PATH, '/\\') . '/v1/' . $generatedRouteName;
$generatedRoutePath = '/api/v1/' . $generatedRouteName . '/:id';

try {
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg(ROOT_PATH . '/bin/create-api.php')
        . ' '
        . escapeshellarg($generatedRoutePath)
        . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $generatedParamDir = $generatedRouteRoot . '/[id]';
    $expectedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    $generatedAllMethods = true;
    foreach ($expectedMethods as $method) {
        $generatedAllMethods = $generatedAllMethods && is_file($generatedParamDir . '/' . $method . '.php');
    }

    check('create-api creates filesystem method files', $exitCode === 0 && $generatedAllMethods);

    $generatedResponse = (new Api())->dispatch(Request::create(
        method: 'POST',
        path: '/api/v1/' . $generatedRouteName . '/123',
        headers: [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => app(\MicroPHP\Security\Csrf::class)->token(),
        ],
        rawBody: '{"name":"Ada"}',
    ));
    $generatedPayload = json_decode($generatedResponse->body(), true);
    check(
        'generated POST method file dispatches through filesystem API routing',
        $generatedResponse->status() === 201
        && ($generatedPayload['data']['method'] ?? null) === 'POST'
        && ($generatedPayload['data']['params']['id'] ?? null) === '123'
        && ($generatedPayload['data']['body']['name'] ?? null) === 'Ada'
    );

    $secondOutput = [];
    $secondExitCode = 0;
    exec($command, $secondOutput, $secondExitCode);
    check(
        'create-api refuses to overwrite method files without --force',
        $secondExitCode !== 0 && str_contains(implode("\n", $secondOutput), 'Use --force')
    );
} finally {
    remove_tree($generatedRouteRoot);
}

echo "== Component Generator ==\n";
$generatedComponentName = 'CodexAssetProbe' . str_replace('.', '', uniqid('', true));
$generatedComponentAssetName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $generatedComponentName) ?? $generatedComponentName);
$generatedComponentRoot = rtrim(COMPONENTS_PATH, '/\\') . '/' . $generatedComponentAssetName;

try {
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg(ROOT_PATH . '/bin/create-component.php')
        . ' '
        . escapeshellarg($generatedComponentName)
        . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    check(
        'create-component keeps class, template, CSS, and JS together in app/components',
        $exitCode === 0
        && is_file($generatedComponentRoot . '/' . $generatedComponentName . '.php')
        && is_file($generatedComponentRoot . '/view.micro.php')
        && is_file($generatedComponentRoot . '/style.css')
        && is_file($generatedComponentRoot . '/script.js')
    );
} finally {
    remove_tree($generatedComponentRoot);
}

echo "== Frontend Router Middleware ==\n";
$frontendResponse = (new Router(Request::create('GET', '/home')))
    ->middleware(function (Request $request, callable $next): Response {
        return $next($request)->withHeader('X-Frontend-Middleware', 'yes');
    })
    ->dispatch();
check('frontend router renders through middleware pipeline', str_contains($frontendResponse->body(), '<h2>Homepage</h2>'));
check('frontend middleware decorates the page response', ($frontendResponse->headers()['X-Frontend-Middleware'] ?? null) === 'yes');

echo "== Application Front Controller ==\n";
$application = new Application(app());
$publicAssetName = '__public_smoke_' . str_replace('.', '_', uniqid('', true)) . '.txt';
$publicAssetPath = rtrim(PUBLIC_ASSETS_PATH, '/\\') . '/' . $publicAssetName;
file_put_contents($publicAssetPath, 'public asset ok');

$assetResponse = $application->handle(Request::create('GET', '/assets/application/css/global.css'));
$pageAssetResponse = $application->handle(Request::create('GET', '/assets/pages/posts/style.css'));
$componentAssetResponse = $application->handle(Request::create('GET', '/assets/components/button/style.css'));
$standalonePublicAssetResponse = $application->handle(Request::create('GET', '/assets/' . $publicAssetName));
$blockedAsset = $application->handle(Request::create('GET', '/assets/application/%2e%2e/config/app.php'));
$assetPost = $application->handle(Request::create('POST', '/assets/application/css/global.css'));
$appSourceResponse = $application->handle(Request::create('GET', '/app/BlogRepository.php'));
$frameworkSourceResponse = $application->handle(Request::create('GET', '/src/Application.php'));
unlink($publicAssetPath);

check('application serves virtual app assets', $assetResponse->status() === 200 && str_contains($assetResponse->headers()['Content-Type'] ?? '', 'text/css'));
check('application serves virtual page assets from app/pages', $pageAssetResponse->status() === 200 && str_contains($pageAssetResponse->headers()['Content-Type'] ?? '', 'text/css'));
check('application serves virtual component assets from app/components', $componentAssetResponse->status() === 200 && str_contains($componentAssetResponse->headers()['Content-Type'] ?? '', 'text/css'));
check('application serves standalone public assets from public/assets', $standalonePublicAssetResponse->status() === 200 && $standalonePublicAssetResponse->body() === 'public asset ok');
check('application rejects asset traversal attempts', $blockedAsset->status() === 404);
check('application rejects unsafe asset methods', $assetPost->status() === 405 && ($assetPost->headers()['Allow'] ?? null) === 'GET, HEAD');
check('application does not expose app source files', $appSourceResponse->status() === 404);
check('application does not expose framework source files', $frameworkSourceResponse->status() === 404);

echo "== Database Drivers ==\n";
$smokeDatabaseFile = tempnam(sys_get_temp_dir(), 'microphp-smoke-db-');
$smokePdo = new PDO('sqlite:' . $smokeDatabaseFile, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$smokePdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT, created_at TEXT)');
$smokePdo->exec('CREATE TABLE uzytkownik (id INTEGER PRIMARY KEY, imie TEXT)');
$smokePdo->exec('CREATE TABLE wypozyczenie (id INTEGER PRIMARY KEY, id_uzytkownik INTEGER)');
$smokePdo->exec("INSERT INTO uzytkownik (id, imie) VALUES (1, 'Ada')");
$smokePdo->exec('INSERT INTO wypozyczenie (id, id_uzytkownik) VALUES (1, 1)');
Database::usePdo($smokePdo);
check('recognizes MariaDB driver', DbDriver::fromName('mariadb') === DbDriver::MariaDb);
check('recognizes SQL Server aliases', DbDriver::fromName('sqlserver') === DbDriver::SqlServer && DbDriver::fromName('mssql') === DbDriver::SqlServer);
check('recognizes MongoDB aliases', DbDriver::fromName('mongodb') === DbDriver::MongoDb && DbDriver::fromName('mongo') === DbDriver::MongoDb);
check('MariaDB uses the MySQL PDO DSN', DbDriver::MariaDb->buildDsn('db.local', 'app', port: '3307') === 'mysql:host=db.local;dbname=app;port=3307;charset=utf8mb4');
check('SQL Server builds a pdo_sqlsrv DSN', DbDriver::SqlServer->buildDsn('sql.local', 'app', port: '1433') === 'sqlsrv:Server=sql.local,1433;Database=app');
check('MongoDB builds a MongoDB URI', DbDriver::MongoDb->buildDsn('mongo.local', 'app', port: '27017') === 'mongodb://mongo.local:27017');

$sqlServerTop = Database::table('posts')
    ->select(['id', 'title'])
    ->limit(5)
    ->toSql(DbDriver::SqlServer)['sql'];
check('SQL Server limit compiles to SELECT TOP', $sqlServerTop === 'SELECT TOP (5) id, title FROM posts');

$sqlServerOffset = Database::table('posts')
    ->select('id')
    ->orderBy('id', 'DESC')
    ->limit(10, 20)
    ->toSql(DbDriver::SqlServer)['sql'];
check('SQL Server offset compiles to OFFSET FETCH', $sqlServerOffset === 'SELECT id FROM posts ORDER BY id DESC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY');

$collectionRequiresMongo = false;
try {
    Database::collection('posts');
} catch (\LogicException $e) {
    $collectionRequiresMongo = str_contains($e->getMessage(), 'DB_DRIVER=mongodb');
}
check('MongoDB collection API rejects SQL drivers clearly', $collectionRequiresMongo);

$unsafeTableBlocked = false;
try {
    Database::table('users; DROP TABLE users')->toSql();
} catch (\InvalidArgumentException $e) {
    $unsafeTableBlocked = str_contains($e->getMessage(), 'Table name');
}
check('query builder rejects unsafe table identifiers', $unsafeTableBlocked);

$unsafeWhereBlocked = false;
try {
    Database::table('posts')->where(['name DESC; DROP TABLE users' => 'Ada'])->toSql();
} catch (\InvalidArgumentException $e) {
    $unsafeWhereBlocked = str_contains($e->getMessage(), 'WHERE column');
}
check('query builder rejects unsafe where identifiers', $unsafeWhereBlocked);

$unsafeOperatorBlocked = false;
try {
    Database::table('posts')->where(['id' => ['OR 1=1', 1]])->toSql();
} catch (\InvalidArgumentException $e) {
    $unsafeOperatorBlocked = str_contains($e->getMessage(), 'WHERE operator');
}
check('query builder rejects unsupported operators', $unsafeOperatorBlocked);

$unsafeOrderBlocked = false;
try {
    Database::table('posts')->orderBy('id = 1 OR 1=1')->toSql();
} catch (\InvalidArgumentException $e) {
    $unsafeOrderBlocked = str_contains($e->getMessage(), 'ORDER BY column');
}
check('query builder rejects unsafe order columns', $unsafeOrderBlocked);

$massUpdateBlocked = false;
try {
    Database::update('posts', ['title' => 'Unsafe'], []);
} catch (\InvalidArgumentException $e) {
    $massUpdateBlocked = str_contains($e->getMessage(), 'requires at least one condition');
}
check('Database::update rejects empty conditions', $massUpdateBlocked);

$massDeleteBlocked = false;
try {
    Database::delete('posts', []);
} catch (\InvalidArgumentException $e) {
    $massDeleteBlocked = str_contains($e->getMessage(), 'requires at least one condition');
}
check('Database::delete rejects empty conditions', $massDeleteBlocked);

echo "== QueryBuilder (via Database::table) ==\n";
$transactionOpen = Database::getInstance()->execute('BEGIN TRANSACTION') !== false;
check('opens a transaction for database smoke checks', $transactionOpen);

try {
    $before = Database::table('posts')->count();
    $newId = Database::insert('posts', [
        'title' => 'Smoke test post',
        'content' => 'Written by manual-smoke-test.php',
        'created_at' => date('c'),
    ]);
    check('insert returns a new numeric id', is_int($newId) && $newId > 0);

    $after = Database::table('posts')->count();
    check('row count increased by exactly one after insert', $after === $before + 1);

    $fetched = Database::table('posts')
        ->select(['id', 'title'])
        ->where(['id' => $newId])
        ->first();
    check('first() finds the inserted row by id', $fetched !== null && $fetched['title'] === 'Smoke test post');

    $updated = Database::update('posts', ['title' => 'Updated by smoke test'], ['id' => $newId]);
    check('update reports one row updated', $updated === 1);

    $refetched = Database::table('posts')->where(['id' => $newId])->first();
    check('update is visible on refetch', $refetched['title'] === 'Updated by smoke test');

    $matches = Database::table('posts')
        ->where(['id' => ['>=', $newId]])
        ->get();
    check('operator condition ([operator, value]) matches the row', count($matches) >= 1);

    $joined = Database::join('wypozyczenie', 'uzytkownik', 'wypozyczenie.id_uzytkownik', 'uzytkownik.id')
        ->select(['wypozyczenie.id AS lend_id', 'uzytkownik.imie AS reader_name'])
        ->limit(1)
        ->get();
    check(
        'Database::join supports safe selected-column aliases',
        ($joined[0]['lend_id'] ?? null) === 1 && ($joined[0]['reader_name'] ?? null) === 'Ada'
    );

    $deleted = Database::delete('posts', ['id' => $newId]);
    check('delete reports one row deleted', $deleted === 1);

    $final = Database::table('posts')->count();
    check('row count back to original after delete', $final === $before);
} finally {
    if ($transactionOpen) {
        Database::getInstance()->execute('ROLLBACK');
    }
}

echo "\n" . ($failures === 0 ? "All checks passed.\n" : "{$failures} check(s) failed.\n");
@unlink($smokeDatabaseFile);
exit($failures === 0 ? 0 : 1);
