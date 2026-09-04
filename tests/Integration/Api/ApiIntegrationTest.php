<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use MicroPHP\Api;
use MicroPHP\Application;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class ApiIntegrationTest extends TestCase
{
    private string $routeName;
    private string $routeDir;

    protected function setUp(): void
    {
        $this->routeName = '__phpunit_' . bin2hex(random_bytes(5));
        $this->routeDir = API_ROUTES_PATH . '/v1/' . $this->routeName;
        mkdir($this->routeDir, 0777, true);
    }

    protected function tearDown(): void { \test_remove_tree($this->routeDir); }

    public function testFilesystemMethodsHeadOptionsAllowAndCallableValidation(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            file_put_contents($this->routeDir . '/' . $method . '.php', '<?php use MicroPHP\\Http\\Response; return fn () => Response::json(["method" => "' . $method . '"]);');
        }
        file_put_contents($this->routeDir . '/HEAD.php', '<?php use MicroPHP\\Http\\Response; return fn () => Response::text("explicit head")->withHeader("X-Head", "yes");');
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = (new Api())->dispatch($this->apiRequest($method, '/api/v1/' . $this->routeName));
            self::assertSame($method, json_decode($response->body(), true)['method']);
        }
        $head = (new Api())->dispatch(Request::create('HEAD', '/api/v1/' . $this->routeName));
        self::assertSame('', $head->body());
        self::assertSame('yes', $head->header('x-head'));
        $options = (new Api())->dispatch(Request::create('OPTIONS', '/api/v1/' . $this->routeName));
        self::assertSame(204, $options->status());
        self::assertSame('GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS', $options->header('allow'));

        file_put_contents($this->routeDir . '/OPTIONS.php', '<?php use MicroPHP\\Http\\Response; return fn () => Response::noContent()->withHeader("X-Options", "explicit");');
        $explicitOptions = (new Api())->dispatch(Request::create('OPTIONS', '/api/v1/' . $this->routeName));
        self::assertSame('explicit', $explicitOptions->header('x-options'));

        unlink($this->routeDir . '/POST.php');
        $notAllowed = (new Api())->dispatch($this->apiRequest('POST', '/api/v1/' . $this->routeName));
        self::assertSame(405, $notAllowed->status());
        self::assertStringNotContainsString('POST', (string) $notAllowed->header('allow'));
    }

    public function testHeadFallbackAndAutomaticOptions(): void
    {
        file_put_contents($this->routeDir . '/GET.php', '<?php use MicroPHP\\Http\\Response; return fn () => Response::text("get body")->withHeader("X-Get", "yes");');
        $head = (new Api())->dispatch(Request::create('HEAD', '/api/v1/' . $this->routeName));
        self::assertSame('', $head->body());
        self::assertSame('yes', $head->header('x-get'));
        self::assertSame('GET, HEAD, OPTIONS', (new Api())->dispatch(Request::create('OPTIONS', '/api/v1/' . $this->routeName))->header('allow'));
    }

    public function testNonCallableFilesystemHandlerBecomesSafeServerError(): void
    {
        file_put_contents($this->routeDir . '/GET.php', '<?php return ["not" => "callable"];');
        $response = (new Application(app(), api: new Api(app())))->handle(
            Request::create('GET', '/api/v1/' . $this->routeName)
        );
        self::assertSame(500, $response->status());
        self::assertSame('INTERNAL_SERVER_ERROR', json_decode($response->body(), true)['error']['code']);
        self::assertStringNotContainsString('callable', $response->body());
    }

    public function testHandlerDependencyInjectionAndInheritedMiddlewareShortCircuit(): void
    {
        file_put_contents($this->routeDir . '/GET.php', '<?php use MicroPHP\\Container; use MicroPHP\\Http\\Request; use MicroPHP\\Http\\Response; return fn (Request $request, Container $container) => Response::json(["ok" => $container instanceof Container]);');
        file_put_contents($this->routeDir . '/_middleware.php', '<?php use MicroPHP\\Http\\Response; return fn ($request, $next) => $request->header("X-Block") ? Response::error("Blocked", 403, "BLOCKED") : $next($request)->withHeader("X-Middleware", "yes");');
        $response = (new Api())->dispatch(Request::create('GET', '/api/v1/' . $this->routeName));
        self::assertTrue(json_decode($response->body(), true)['ok']);
        self::assertSame('yes', $response->header('x-middleware'));
        $blocked = (new Api())->dispatch(Request::create('GET', '/api/v1/' . $this->routeName, headers: ['X-Block' => '1']));
        self::assertSame(403, $blocked->status());
    }

    public function testVersionTraversalNeverIncludesUnrelatedPhp(): void
    {
        $probe = PAGES_PATH . '/__api_traversal_probe.php';
        file_put_contents($probe, '<?php $GLOBALS["api_traversal_probe"] = true;');
        unset($GLOBALS['api_traversal_probe']);
        try {
            foreach ([
                '/api/../pages', '/api/../api/v1/status', '/api/%2e%2e/pages', '/api/%252e%252e/pages',
                '/api/..\\pages', '/api/%5c..%5cpages', '/api/v1/../../config', '/api/v1/%2e%2e/status',
            ] as $path) {
                $response = (new Api())->dispatch(Request::create('GET', $path));
                self::assertContains($response->status(), [400, 404], $path);
            }
            self::assertArrayNotHasKey('api_traversal_probe', $GLOBALS);
        } finally {
            @unlink($probe);
        }
    }

    public function testProductionErrorsDoNotLeakSecrets(): void
    {
        $secret = 'password=hunter2 SELECT * FROM users /srv/private/config.php';
        Api::get('/' . $this->routeName . '/explode', static function () use ($secret): never { throw new \RuntimeException($secret); });
        $application = new Application(app(), api: new Api(app()));
        $response = $application->handle(Request::create('GET', '/api/v1/' . $this->routeName . '/explode'));
        self::assertSame(500, $response->status());
        foreach (['hunter2', 'SELECT *', '/srv/private', 'RuntimeException'] as $leak) {
            self::assertStringNotContainsString($leak, $response->body());
        }
        self::assertSame('INTERNAL_SERVER_ERROR', json_decode($response->body(), true)['error']['code']);
    }

    public function testApiWriteRequestsRequireCsrfByDefault(): void
    {
        file_put_contents($this->routeDir . '/POST.php', '<?php use MicroPHP\\Http\\Response; return fn () => Response::json(["ran" => true]);');

        $missing = (new Api())->dispatch(Request::create('POST', '/api/v1/' . $this->routeName));
        self::assertSame(419, $missing->status());

        $valid = (new Api())->dispatch($this->apiRequest('POST', '/api/v1/' . $this->routeName));
        self::assertSame(200, $valid->status());
    }

    private function apiRequest(string $method, string $path): Request
    {
        $headers = [];
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $headers['X-CSRF-Token'] = app(Csrf::class)->token();
        }

        return Request::create($method, $path, headers: $headers);
    }
}
