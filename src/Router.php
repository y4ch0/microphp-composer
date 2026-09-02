<?php
/**
 * MicroPHP Framework
 * Frontend filesystem router for classic PHP pages and .micro.php templates.
 */

namespace MicroPHP;

use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\MiddlewarePipeline;
use MicroPHP\Http\Middleware\CsrfMiddleware;
use MicroPHP\Http\Middleware\GuardMiddleware;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Routing\RouteResolver;
use MicroPHP\Routing\MiddlewareResolver;

class Router
{
    protected string $uri;
    /** @var string[] */
    protected array $segments = [];
    protected Request $request;
    private AssetManager $assets;
    /** @var array<int,MiddlewareInterface|callable> */
    protected array $middleware = [];

    public function __construct(?Request $request = null)
    {
        $this->assets = new AssetManager();
        $this->setRequest($request ?? Request::fromGlobals());
    }

    /**
     * The current request object, usable from pages, guards, and middleware.
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * Register middleware for this router instance.
     */
    public function middleware(mixed $middleware): self
    {
        $this->middleware = array_merge(
            $this->middleware,
            MiddlewarePipeline::normalize($middleware, 'Router::middleware')
        );

        return $this;
    }

    /**
     * Alias for Router::middleware().
     */
    public function pipe(mixed $middleware): self
    {
        return $this->middleware($middleware);
    }

    private function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->uri = $request->path();
        $this->segments = $request->segments();
    }

    private static function pagesPath(): string
    {
        return defined('PAGES_PATH') ? PAGES_PATH : ROOT_PATH . '/app/pages';
    }

    private static function pagesUrl(): string
    {
        return defined('PAGES_URL') ? PAGES_URL : '/assets/pages';
    }

    private static function layoutsPath(): string
    {
        return defined('LAYOUTS_PATH') ? LAYOUTS_PATH : ROOT_PATH . '/app/layouts';
    }

    private static function appAssetsPath(): string
    {
        return defined('APP_ASSETS_PATH') ? APP_ASSETS_PATH : ROOT_PATH . '/app/assets';
    }

    private static function appAssetsUrl(): string
    {
        return defined('APP_ASSETS_URL') ? APP_ASSETS_URL : '/assets/application';
    }

    /**
     * Resolve and send the current frontend request.
     */
    public function handleRequest(): void
    {
        $this->dispatch()->send();
    }

    /**
     * Resolve the current frontend request into a Response.
     */
    public function dispatch(?Request $request = null): Response
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            @trigger_error('Router::dispatch() is deprecated; use PageDispatcher.', E_USER_DEPRECATED);
        }
        return (new PageDispatcher($this))->dispatch($request);
    }

    public function dispatchCanonical(?Request $request = null): Response
    {
        $this->assets = new AssetManager();
        if ($request !== null) {
            $this->setRequest($request);
        }

        try {
            $pipeline = new MiddlewarePipeline($this->configuredMiddleware());

            return $pipeline->handle(
                $this->request,
                fn (Request $request): Response => $this->dispatchPage($request)
            );
        } finally {
            // Release all request-owned registrations even when dispatch throws.
            $this->assets = new AssetManager();
        }
    }

    private function dispatchPage(Request $request): Response
    {
        $this->setRequest($request);

        $match = (new RouteResolver())->resolve(
            root: self::pagesPath(),
            path: '/' . $request->path(),
            defaultSegments: ['home']
        );

        if ($match === null) {
            return $this->notFoundResponse();
        }

        $params = $match->params;
        $assetPathSegments = $match->segments;
        $currentPath = $match->directory;
        $pageRequest = $request->withRouteParams($params);
        $this->setRequest($pageRequest);

        $pagePhpFile = $currentPath . '/index.php';
        $pageMicroFile = $currentPath . '/index.micro.php';
        $baseDir = realpath(self::pagesPath());

        if (
            $baseDir === false ||
            (!file_exists($pagePhpFile) && !file_exists($pageMicroFile)) ||
            (file_exists($pagePhpFile) && !$this->pathIsInside($pagePhpFile, $baseDir)) ||
            (file_exists($pageMicroFile) && !$this->pathIsInside($pageMicroFile, $baseDir))
        ) {
            return $this->notFoundResponse('The requested page could not be found.');
        }

        $pageDir = $currentPath;
        $accessMode = $this->pageAccessMode();

        $pageMiddleware = [];
        if ($accessMode === 'guard' || $accessMode === 'both') {
            foreach ($this->findInheritedGuards($pageDir)['guards'] as $guardConfig) {
                $pageMiddleware[] = new GuardMiddleware($this, $guardConfig['handler']);
            }
        }
        if ($accessMode === 'middleware' || $accessMode === 'both') {
            $pageMiddleware = array_merge($pageMiddleware, $this->findInheritedMiddleware($pageDir)['middleware']);
        }

        $pipeline = new MiddlewarePipeline($pageMiddleware);

        return $pipeline->handle(
            $pageRequest,
            fn (Request $request): Response => $this->renderPageResponse(
                $pagePhpFile,
                $pageMicroFile,
                $assetPathSegments,
                $request
            )
        );
    }

    /**
     * @return array<int,MiddlewareInterface|callable>
     */
    private function configuredMiddleware(): array
    {
        return array_merge(
            [new CsrfMiddleware(function_exists('app') ? app(\MicroPHP\Security\Csrf::class) : new \MicroPHP\Security\Csrf())],
            MiddlewarePipeline::normalize(
                defined('FRONTEND_MIDDLEWARE') ? FRONTEND_MIDDLEWARE : [],
                'FRONTEND_MIDDLEWARE'
            ),
            $this->middleware
        );
    }

    /**
     * Find guard configurations inherited from the page directory tree.
     *
     * @return array{guards: array<int,array<string,mixed>>}
     */
    private function findInheritedGuards(string $startDir): array
    {
        $settings = ['guards' => []];
        $currentDir = $startDir;
        $pagesRoot = realpath(self::pagesPath());

        if ($pagesRoot === false) {
            return $settings;
        }

        while (($realCurrent = realpath($currentDir)) !== false && $this->pathIsInside($realCurrent, $pagesRoot)) {
            $guardFile = $currentDir . '/_guard.php';
            if (file_exists($guardFile)) {
                $guardConfig = include $guardFile;
                if (is_array($guardConfig) && isset($guardConfig['handler']) && is_callable($guardConfig['handler'])) {
                    array_unshift($settings['guards'], $guardConfig);
                    if (($guardConfig['override'] ?? false) === true) {
                        break;
                    }
                }
            }

            if ($realCurrent === $pagesRoot) {
                break;
            }

            $currentDir = dirname($currentDir);
        }

        return $settings;
    }

    /**
     * Find middleware inherited from the page directory tree.
     *
     * _middleware.php may return one middleware, a list, or:
     * ['middleware' => [...], 'override' => true|false]
     *
     * @return array{middleware: array<int,MiddlewareInterface|callable>}
     */
    private function findInheritedMiddleware(string $startDir): array
    {
        return ['middleware' => (new MiddlewareResolver())->resolve(self::pagesPath(), $startDir)];
    }

    private function pageAccessMode(): string
    {
        $mode = defined('PAGE_ACCESS_MODE') ? strtolower((string) PAGE_ACCESS_MODE) : 'both';

        return in_array($mode, ['guard', 'middleware', 'both'], true) ? $mode : 'both';
    }

    /**
     * @param string[] $assetPathSegments
     */
    private function renderPageResponse(
        string $pagePhpFile,
        string $pageMicroFile,
        array $assetPathSegments,
        Request $request
    ): Response {
        $meta = null;
        $layout = null;
        $params = $request->routeParams();
        $view = new View($this->assets);

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            if (file_exists($pagePhpFile)) {
                ob_start();
                require $pagePhpFile;
                $content = ob_get_clean();
            } elseif (file_exists($pageMicroFile)) {
                $template = str_replace(rtrim(self::pagesPath(), '/\\') . '/', '', $pageMicroFile);
                $template = str_replace(['/', '.micro.php'], ['.', ''], $template);

                $content = $view->renderNamed($template, [
                    'params' => $params,
                    'request' => $request,
                    'meta' => $meta,
                    'layout' => $layout,
                ]);
            } else {
                while (ob_get_level() > $bufferLevel) {
                    ob_end_clean();
                }
                return $this->notFoundResponse('The requested page could not be found.');
            }

            $layout = $layout ?? 'main';
            $meta = array_merge($this->defaultMeta(), $meta ?? []);
            $this->registerGlobalAssets();
            $this->registerPageAssets($assetPathSegments);
            $assets = $this->assets;

            $layoutFile = rtrim(self::layoutsPath(), '/\\') . '/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                while (ob_get_level() > $bufferLevel) {
                    ob_end_clean();
                }
                return $this->notFoundResponse('Layout file not found: ' . htmlspecialchars($layout, ENT_QUOTES, 'UTF-8'));
            }

            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $body = (new LayoutRenderer())->render($layoutFile, [
                'meta' => $meta,
                'content' => $content,
                'request' => $request,
                'view' => $view,
                'assets' => $assets,
            ]);
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $e;
        }

        $status = http_response_code();
        $status = is_int($status) && $status >= 100 ? $status : 200;

        return Response::html($body, $status);
    }

    public function notFoundResponse(string $message = 'The page you requested could not be found.'): Response
    {
        return $this->renderErrorResponse(
            404,
            '404 Not Found',
            'Page not found.',
            '<h1>404 Not Found</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        );
    }

    public function forbiddenResponse(string $message = "You don't have permission to access this page."): Response
    {
        return $this->renderErrorResponse(
            403,
            '403 Forbidden',
            'Access denied.',
            '<h1>403 Forbidden</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        );
    }

    public function serveNotFound(string $message = 'The page you requested could not be found.'): never
    {
        $this->notFoundResponse($message)->send();
        exit;
    }

    public function serveForbidden(string $message = "You don't have permission to access this page."): never
    {
        $this->forbiddenResponse($message)->send();
        exit;
    }

    private function renderErrorResponse(int $status, string $title, string $description, string $content): Response
    {
        $meta = [
            'title' => $title,
            'description' => $description,
            'icon' => rtrim(self::appAssetsUrl(), '/') . '/img/favicon.ico',
        ];
        $this->registerGlobalAssets();
        $assets = $this->assets;
        $view = new View($assets);

        $layoutFile = rtrim(self::layoutsPath(), '/\\') . '/main.php';
        if (!file_exists($layoutFile)) {
            return Response::html($content, $status);
        }

        $body = (new LayoutRenderer())->render($layoutFile, [
            'meta' => $meta,
            'content' => $content,
            'request' => $this->request,
            'view' => $view,
            'assets' => $assets,
        ]);

        return Response::html($body, $status);
    }

    /** @return array<string,string> */
    private function defaultMeta(): array
    {
        return [
            'title' => PROJECT_NAME,
            'description' => 'A website powered by the MicroPHP framework.',
            'icon' => rtrim(self::appAssetsUrl(), '/') . '/img/favicon.ico',
        ];
    }

    private function registerGlobalAssets(): void
    {
        $globalAssets = require ROOT_PATH . '/config/assets.php';

        foreach ($globalAssets['css'] as $cssPath) {
            $this->assets->registerStyleUrl((string) $cssPath);
        }
        if (file_exists(rtrim(self::appAssetsPath(), '/\\') . '/css/global.css')) {
            $this->assets->registerStyleFile(rtrim(self::appAssetsPath(), '/\\') . '/css/global.css');
        }
        foreach ($globalAssets['js'] as $jsPath) {
            $this->assets->registerScriptUrl((string) $jsPath);
        }
    }

    /**
     * @param string[] $assetPathSegments
     * @return array{0: string, 1: string}
     */
    private function registerPageAssets(array $assetPathSegments): void
    {
        $assetFilePath = rtrim(self::pagesPath(), '/\\') . '/' . implode('/', $assetPathSegments);

        if (file_exists($assetFilePath . '/style.css')) {
            $this->assets->registerStyleFile($assetFilePath . '/style.css');
        }

        if (file_exists($assetFilePath . '/script.js')) {
            $this->assets->registerScriptFile($assetFilePath . '/script.js');
        }
    }

    private function pathIsInside(string $path, string $baseDir): bool
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            return false;
        }

        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $realPath = rtrim($realPath, DIRECTORY_SEPARATOR);

        return $realPath === $baseDir
            || str_starts_with($realPath . DIRECTORY_SEPARATOR, $baseDir . DIRECTORY_SEPARATOR);
    }
}
