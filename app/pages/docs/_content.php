<?php

/**
 * Content and rendering helpers for the MicroPHP documentation pages.
 *
 * This file belongs to the application layer. It is intentionally separate
 * from the framework internals so the documentation can describe MicroPHP
 * without changing MicroPHP itself.
 */

function microphp_docs_code(string $code): string
{
    return '<pre><code>' . htmlspecialchars(trim($code), ENT_QUOTES, 'UTF-8') . '</code></pre>';
}

function microphp_docs_url(string $slug): string
{
    return $slug === 'introduction' ? '/docs' : '/docs/' . $slug;
}

function microphp_docs_pages(): array
{
    static $pages = null;

    if ($pages !== null) {
        return $pages;
    }

    $pages = [
        'introduction' => [
            'title' => 'Introduction',
            'description' => 'What MicroPHP is, why it exists, and how to think about the framework.',
            'content' => <<<'HTML'
<section>
    <h1>MicroPHP Documentation</h1>
    <p>MicroPHP is a small PHP framework for developers who already understand basic PHP and want the missing application structure around it. It keeps pages, templates, components, sessions, routing, API handlers, database access, guards, logging, and simple tooling close to ordinary PHP instead of turning the project into a stack of unrelated packages.</p>
    <p>The idea is simple: learning PHP should be enough to start building a real website. If you need sessions, MicroPHP starts and stores them for you. If you need database access, the query builder lets you read and write records without starting with raw SQL. If you need routing, the filesystem becomes the route map. If you are worried about unsafe URL traversal, the router rejects suspicious segments before loading page files.</p>
    <p>This documentation is served by MicroPHP itself. The route <code>/docs</code> is a normal page directory, and every topic below is served through a dynamic <code>[page]</code> segment. The site also uses the framework layout, component asset queue, metadata injection, session-backed flash messages, and normal HTML output.</p>
</section>

<section>
    <h2>Design Goals</h2>
    <ul>
        <li><strong>Extended PHP, not hidden PHP.</strong> A page can still be an <code>index.php</code> file with HTML and PHP in the same place.</li>
        <li><strong>Convention before ceremony.</strong> Folder names define routes, local assets live next to pages, and components have predictable class and asset paths.</li>
        <li><strong>Useful defaults.</strong> Sessions, layouts, global assets, view cache paths, database configuration, and error handling are bootstrapped centrally.</li>
        <li><strong>Small core surface.</strong> MicroPHP avoids large mandatory dependencies and keeps important mechanisms readable.</li>
        <li><strong>Safer everyday code.</strong> Route path checks, escaped template output, prepared statement parameters, CSRF token helpers, and guard files reduce common mistakes.</li>
    </ul>
</section>

<section>
    <h2>When MicroPHP Fits</h2>
    <p>MicroPHP fits small and medium PHP applications, internal tools, teaching projects, prototypes, CRUD-heavy websites, and projects where direct PHP productivity matters more than a large ecosystem. It works best when you want explicit files and simple conventions rather than a complex runtime.</p>
</section>

<section>
    <h2>Documentation Map</h2>
    <p>Start with installation, then read routing, pages, templates, and components. After that, the API, database, guards, middleware, testing, and Composer sections describe how to grow the project responsibly.</p>
</section>
HTML,
        ],
        'installation' => [
            'title' => 'Installation',
            'description' => 'Requirements, first run, environment settings, and local development workflow.',
            'content' => <<<'HTML'
<section>
    <h1>Installation</h1>
    <p>MicroPHP is a Composer project skeleton. The current package requires PHP 8.1 or newer and uses Composer autoloading for framework classes, application classes, and global helper functions.</p>
</section>

<section>
    <h2>Requirements</h2>
    <ul>
        <li>PHP 8.1 or newer.</li>
        <li>Composer 2.x.</li>
        <li>PDO and the PDO driver for your SQL database. SQLite is the default configuration. MongoDB requires the <code>ext-mongodb</code> PHP extension.</li>
        <li>A web server that routes requests through <code>index.php</code>. Pointing the document root at <code>public/</code> is preferred, but root hosting is supported with the included Apache <code>.htaccess</code> guard.</li>
    </ul>
</section>

<section>
    <h2>Install Dependencies</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
composer install
TEXT)
            . <<<'HTML'
    <p>The autoloader maps <code>MicroPHP\</code> to <code>src/</code>, <code>App\</code> to <code>app/</code>, and always includes <code>src/Helpers.php</code>, so helper functions are available after <code>vendor/autoload.php</code> is loaded by the bootstrap file.</p>
</section>

<section>
    <h2>Run Locally</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php -S localhost:8000 -t public public/index.php

# Convenience mode from the project root:
php -S localhost:8000 index.php
TEXT)
            . <<<'HTML'
    <p>Both commands serve routes without a <code>/public</code> URL prefix. The <code>-t public</code> option is the safest shape because framework, config, database, vendor, and application source files are outside the document root. Root mode is convenient for local development and shared Apache hosting, but it relies on the root <code>.htaccess</code> rules to deny direct access to private directories.</p>
</section>

<section>
    <h2>Environment File</h2>
    <p>Configuration is read from <code>.env</code> when that file exists. New Composer projects run the setup wizard automatically, and existing projects can copy <code>.env.example</code> or rerun the wizard.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
PROJECT_NAME="MicroPHP Application"
API_SERVICE_ENABLED=true
API_CSRF_ENABLED=true
PAGE_ACCESS_MODE=both
SESSION_COOKIE_SAMESITE=Lax
SECURITY_HEADERS_ENABLED=true
DB_DRIVER=sqlite
DB_PATH=database/library.db
VIEW_CACHE_TRUST=false
TEXT)
            . <<<'HTML'
    <p>Use <code>APP_DEBUG=false</code> outside local development. In production, detailed exceptions are logged but not rendered to visitors.</p>
</section>

<section>
    <h2>Create a Project with Composer</h2>
    <p>After the package is published through Packagist or exposed through a configured VCS/path repository, a new application can be created with Composer:</p>
HTML
            . microphp_docs_code(<<<'TEXT'
composer create-project yacho/microphp my-app
TEXT)
            . <<<'HTML'
    <p>The package setup script asks for the project display name, trusted application URL, database driver, and related configuration before writing <code>.env</code> with owner-only permissions. Password and DSN input is hidden. To overwrite an existing environment file and rerun the prompts, use <code>composer run microphp:setup -- --force</code>. The setup wizard always requires an interactive terminal; blank answers are rejected, and optional empty values must be entered explicitly as <code>-</code>.</p>
</section>
HTML,
        ],
        'project-structure' => [
            'title' => 'Project Structure',
            'description' => 'What the main directories are for and how to organize application work.',
            'content' => <<<'HTML'
<section>
    <h1>Project Structure</h1>
    <p>MicroPHP separates the framework classes from the application files while keeping both visible and easy to inspect.</p>
</section>

<section>
    <h2>Root Files</h2>
    <table>
        <thead>
            <tr>
                <th>Path</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>index.php</code></td>
                <td>Compatibility front controller for root-hosted development or shared hosting.</td>
            </tr>
            <tr>
                <td><code>public/index.php</code></td>
                <td>The preferred public web entry point when hosting can point the document root at <code>public/</code>.</td>
            </tr>
            <tr>
                <td><code>.htaccess</code></td>
                <td>Apache rewrite and deny rules for root-hosted setups.</td>
            </tr>
            <tr>
                <td><code>bootstrap/app.php</code></td>
                <td>Loads Composer, configuration, the service container, database and logger singletons, and the central exception handler.</td>
            </tr>
            <tr>
                <td><code>composer.json</code></td>
                <td>Defines the package name, project type, PHP requirement, autoloading, helper file, CLI binaries, and development dependencies.</td>
            </tr>
            <tr>
                <td><code>config/app.php</code></td>
                <td>Defines environment, database, path, asset, API, and view cache constants.</td>
            </tr>
            <tr>
                <td><code>config/assets.php</code></td>
                <td>Lists global CSS and JavaScript files loaded on every frontend page.</td>
            </tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Framework Source</h2>
    <table>
        <thead>
            <tr>
                <th>Path</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>src/Router.php</code></td>
                <td>Frontend filesystem router for pages, dynamic segments, guard inheritance, layouts, metadata, and scoped assets.</td>
            </tr>
            <tr>
                <td><code>src/View.php</code></td>
                <td>Blade-inspired <code>.micro.php</code> template renderer and compiler.</td>
            </tr>
            <tr>
                <td><code>src/ViewCache.php</code></td>
                <td>Compiled template cache manager with warm, clear, and stats operations.</td>
            </tr>
            <tr>
                <td><code>src/Component.php</code></td>
                <td>Base class for class-based components and their scoped assets.</td>
            </tr>
            <tr>
                <td><code>src/Api.php</code></td>
                <td>Versioned API router for <code>/api/...</code> requests and internal API calls.</td>
            </tr>
            <tr>
                <td><code>src/Database.php</code></td>
                <td>PDO connection manager, raw query facade, and write methods.</td>
            </tr>
            <tr>
                <td><code>src/QueryBuilder.php</code></td>
                <td>Fluent query builder for common SELECT, JOIN, INSERT, UPDATE, DELETE, count, first, and list operations.</td>
            </tr>
            <tr>
                <td><code>src/Http/</code></td>
                <td>Request, Response, middleware contract, middleware pipeline, and example middleware implementations.</td>
            </tr>
            <tr>
                <td><code>src/Enums/</code></td>
                <td>Typed enums for database drivers and HTTP methods.</td>
            </tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Application Source</h2>
    <table>
        <thead>
            <tr>
                <th>Path</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>app/pages/</code></td>
                <td>Frontend pages. Folders become URL segments. Each route directory contains <code>index.php</code> or <code>index.micro.php</code>.</td>
            </tr>
            <tr>
                <td><code>app/layouts/</code></td>
                <td>Page layouts. The default layout is <code>main.php</code>.</td>
            </tr>
            <tr>
                <td><code>app/components/</code></td>
                <td>Component folders. Each component keeps its PHP class, template, stylesheet, and script together.</td>
            </tr>
            <tr>
                <td><code>app/api/</code></td>
                <td>Versioned API route files loaded by <code>MicroPHP\Api</code>.</td>
            </tr>
            <tr>
                <td><code>app/assets/</code></td>
                <td>Application-owned global assets such as app CSS and images.</td>
            </tr>
            <tr>
                <td><code>public/assets/</code></td>
                <td>Standalone public assets such as favicons, vendor CSS/JS libraries, fonts, and downloadable files.</td>
            </tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Runtime Directories</h2>
    <p><code>var/cache/views</code> stores compiled templates. <code>var/sessions</code> stores PHP session files when writable. <code>var/log</code> stores application logs created by the built-in logger.</p>
</section>

<section>
    <h2>Organization Rule</h2>
    <p>Put application behavior in application files first. Reach into framework internals only when you are intentionally changing the framework itself. For most projects, pages, components, API handlers, guards, middleware classes, and services are enough.</p>
</section>
HTML,
        ],
        'routing' => [
            'title' => 'Routing',
            'description' => 'Filesystem routing, dynamic segments, default pages, route parameters, and 404 behavior.',
            'content' => <<<'HTML'
<section>
    <h1>Routing</h1>
    <p>MicroPHP uses filesystem routing for frontend pages. The URL path is split into segments, and each segment is matched against a directory in <code>app/pages</code>.</p>
</section>

<section>
    <h2>Default Route</h2>
    <p>The empty path <code>/</code> is mapped to the <code>home</code> page. This means the default homepage lives here:</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/home/index.php
TEXT)
            . <<<'HTML'
</section>

<section>
    <h2>Static Routes</h2>
    <p>A route is a directory with either <code>index.php</code> or <code>index.micro.php</code>.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/about/index.php       -> /about
app/pages/admin/users/index.php -> /admin/users
app/pages/docs/index.php        -> /docs
TEXT)
            . <<<'HTML'
</section>

<section>
    <h2>Dynamic Segments</h2>
    <p>Wrap a directory name in square brackets to capture that part of the URL.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/article/[articleId]/index.php
TEXT)
            . microphp_docs_code(<<<'TEXT'
/article/101
TEXT)
            . microphp_docs_code(<<<'PHP'
<?php

$articleId = $request->route('articleId');

?>

<h1>Article <?php echo $articleId; ?></h1>
PHP)
            . <<<'HTML'
    <p>The shared route resolver stores captured values on the request. Read them with <code>$request-&gt;route('articleId')</code> or <code>$request-&gt;routeParams()</code>.</p>
</section>

<section>
    <h2>Request Object Access</h2>
    <p>Inside a page included by the router, the current router instance is available as <code>$this</code>. You can read the current request without touching globals directly.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$request = $this->request();
$sort = $request->input('sort', 'newest');

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Route Safety</h2>
    <p>The router rejects URL segments that contain <code>..</code> or a slash inside a segment. It also verifies that the resolved page file stays inside <code>PAGES_PATH</code>. These checks protect file-based routing from path traversal mistakes.</p>
</section>

<section>
    <h2>404 Pages</h2>
    <p>If a route cannot be matched to an <code>index.php</code> or <code>index.micro.php</code> page, the router renders a 404 response. You can customize the user-facing 404 route with:</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/404/index.php
TEXT)
            . <<<'HTML'
</section>
HTML,
        ],
        'pages-and-layouts' => [
            'title' => 'Pages and Layouts',
            'description' => 'Classic PHP pages, MicroPHP templates, metadata, layouts, and page-level assets.',
            'content' => <<<'HTML'
<section>
    <h1>Pages and Layouts</h1>
    <p>A page is the main unit of frontend work in MicroPHP. It can be a classic PHP file or a <code>.micro.php</code> template.</p>
</section>

<section>
    <h2>Classic PHP Page</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$meta['title'] = 'Dashboard';
$meta['description'] = 'Admin dashboard.';

?>

<h1>Dashboard</h1>
<p>Welcome back.</p>
PHP)
            . <<<'HTML'
    <p>Classic pages are useful when the page naturally mixes request handling, data loading, and markup. Always escape user-provided values with <code>htmlspecialchars()</code>.</p>
</section>

<section>
    <h2>MicroPHP Template Page</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<?php
$username = 'Ada';
?>

<h1>Welcome, {{ $username }}</h1>
HTMLCODE)
            . <<<'HTML'
    <p>A file named <code>index.micro.php</code> is compiled by the MicroPHP view engine. Use it when you want Blade-style echo, control structures, includes, components, and form helpers.</p>
</section>

<section>
    <h2>Metadata</h2>
    <p>Set <code>$meta</code> in the page before output. The router merges it with framework defaults and passes it to the layout.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$meta['title'] = 'Library';
$meta['description'] = 'Browse books and active loans.';
$meta['icon'] = '/assets/img/favicon.ico';

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Layouts</h2>
    <p>The default layout is <code>main</code>. Change it by setting <code>$layout</code> in the page.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$layout = 'admin';
$meta['title'] = 'Manage User';

?>
PHP)
            . <<<'HTML'
    <p>Layout files live in <code>app/layouts</code>. They receive <code>$content</code>, <code>$meta</code>, <code>$styles</code>, and <code>$scripts</code> from the router.</p>
</section>

<section>
    <h2>Page-Level CSS and JavaScript</h2>
    <p>Put <code>style.css</code> or <code>script.js</code> next to the page file. The router loads them only for that route.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/posts/index.php
app/pages/posts/style.css
app/pages/posts/script.js
TEXT)
            . <<<'HTML'
    <p>For a dynamic route, assets live beside the dynamic page directory:</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/article/[articleId]/index.php
app/pages/article/[articleId]/style.css
TEXT)
            . <<<'HTML'
</section>

<section>
    <h2>Global Assets</h2>
    <p>Use <code>config/assets.php</code> for CSS or JavaScript that every frontend page should load.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

return [
    'css' => [
        'https://example.com/app.css',
    ],
    'js' => [
        'https://example.com/app.js',
    ],
];
PHP)
            . <<<'HTML'
</section>
HTML,
        ],
        'templates' => [
            'title' => 'Templates',
            'description' => 'Blade-inspired .micro.php templates, escaping, directives, includes, components, and cache.',
            'content' => <<<'HTML'
<section>
    <h1>Templates</h1>
    <p>MicroPHP templates use the <code>.micro.php</code> extension and are compiled into PHP before they are rendered. They are designed for developers who like PHP but want cleaner view syntax for common presentation tasks.</p>
</section>

<section>
    <h2>Escaped Output</h2>
    <p>Double braces escape values with <code>htmlspecialchars()</code>.</p>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<h1>{{ $title }}</h1>
HTMLCODE)
            . <<<'HTML'
    <p>Use this for any value that can contain user-controlled content.</p>
</section>

<section>
    <h2>Raw Output</h2>
    <p>Raw echo is available when you already trust or sanitize the HTML yourself.</p>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
{!! $trustedHtml !!}
HTMLCODE)
            . <<<'HTML'
    <p>Use raw output deliberately. It bypasses escaping.</p>
</section>

<section>
    <h2>PHP Blocks and Imports</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
@php
$items = ['one', 'two', 'three'];
@endphp

@use("MicroPHP\Database")
HTMLCODE)
            . <<<'HTML'
    <p><code>@php</code> and <code>@endphp</code> open and close PHP blocks. <code>@use</code> inserts a PHP <code>use</code> statement for namespaced classes.</p>
</section>

<section>
    <h2>Conditions</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
@if($role === 'admin')
    <p>Admin area.</p>
@elseif($role === 'editor')
    <p>Editor area.</p>
@else
    <p>Standard area.</p>
@endif
HTMLCODE)
            . <<<'HTML'
</section>

<section>
    <h2>Loops</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<ul>
@foreach($posts as $post)
    @continue(!$post['published'])
    <li>{{ $post['title'] }}</li>
@endforeach
</ul>

@for($i = 0; $i < 3; $i++)
    <p>Loop {{ $i }}</p>
@endfor

@while($counter > 0)
    <p>{{ $counter }}</p>
    @php $counter--; @endphp
@endwhile
HTMLCODE)
            . <<<'HTML'
    <p><code>@break</code>, <code>@break(condition)</code>, <code>@continue</code>, and <code>@continue(condition)</code> are supported.</p>
</section>

<section>
    <h2>Includes and Components</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
@include("shared.notice", ["message" => "Saved"])

@component("button", [
    "text" => "Read more",
    "link" => "/docs/components",
    "type" => "secondary",
])
HTMLCODE)
            . <<<'HTML'
    <p>Includes render another view from the pages directory. Components resolve a class from <code>MicroPHP\Components</code> and can queue their own assets.</p>
</section>

<section>
    <h2>Conditional Attributes</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<button @class([
    'btn',
    'btn-primary' => $isPrimary,
    'btn-disabled' => !$enabled,
])>
    Save
</button>

<div @style([
    'color:red' => $error,
    'font-weight:bold' => $important,
    'margin:10px',
])>
    Message
</div>
HTMLCODE)
            . <<<'HTML'
</section>

<section>
    <h2>Form Attribute Helpers</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<input type="text" @value($username) @readonly($locked)>
<input type="checkbox" @checked($enabled)>
<option @value($id) @selected($id === $selectedId)>{{ $name }}</option>
<button @disabled(!$canSubmit)>Submit</button>
HTMLCODE)
            . <<<'HTML'
</section>

<section>
    <h2>CSRF Token Field</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<form method="post">
    @csrf
    <input name="title">
    <button type="submit">Save</button>
</form>
HTMLCODE)
            . <<<'HTML'
    <p>The <code>@csrf</code> directive emits an escaped hidden <code>_token</code> field. Frontend middleware verifies it automatically for state-changing methods.</p>
</section>

<section>
    <h2>Template Cache</h2>
    <p>Compiled templates are stored in <code>var/cache/views</code>. In development, MicroPHP checks whether the source template is newer. In production, <code>VIEW_CACHE_TRUST=true</code> lets the framework trust already-warmed cache files.</p>
</section>
HTML,
        ],
        'components' => [
            'title' => 'Components',
            'description' => 'Class-based components, props, templates, scoped assets, and the component generator.',
            'content' => <<<'HTML'
<section>
    <h1>Components</h1>
    <p>Components are reusable UI units backed by ordinary PHP classes. A component receives props through a typed constructor, renders HTML, and can automatically load its own CSS and JavaScript once per request.</p>
</section>

<section>
    <h2>Component Class</h2>
    <p>Component classes, templates, styles, and scripts live together under <code>app/components/&lt;component-name&gt;</code> and extend <code>MicroPHP\Component</code>.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

namespace MicroPHP\Components;

use MicroPHP\Component;

class AlertBox extends Component
{
    public function __construct(
        protected string $title = 'Notice',
        protected string $message = '',
    ) {}

    public function render(): string
    {
        return $this->view('view.micro.php', [
            'title' => $this->title,
            'message' => $this->message,
        ]);
    }
}
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Component Template and Assets</h2>
    <p>By default, the component directory is inferred from the class name in kebab case.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/components/alert-box/AlertBox.php
app/components/alert-box/view.micro.php
app/components/alert-box/style.css
app/components/alert-box/script.js
TEXT)
            . <<<'HTML'
    <p>If <code>style.css</code> or <code>script.js</code> exists in the component directory, MicroPHP queues it when the component is rendered. The same asset is included only once per request through the virtual <code>/assets/components</code> URL.</p>
</section>

<section>
    <h2>Render from a PHP Page</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

\MicroPHP\View::component('alert-box', [
    'title' => 'Saved',
    'message' => 'The profile was updated.',
]);

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Render from a Template</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
@component("alert-box", [
    "title" => "Saved",
    "message" => "The profile was updated.",
])
HTMLCODE)
            . <<<'HTML'
</section>

<section>
    <h2>Nested Component Names</h2>
    <p>A template name such as <code>forms.input</code> resolves to <code>MicroPHP\Components\Forms\Input</code> and uses files under <code>app/components/forms/input</code>.</p>
</section>

<section>
    <h2>Generate a Component</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php bin/create-component.php AlertBox
php bin/create-component.php Forms/Input
php bin/create-component.php AlertBox --dry-run
php bin/create-component.php AlertBox --force
TEXT)
            . <<<'HTML'
    <p>The generator creates the PHP class, <code>view.micro.php</code>, <code>style.css</code>, and <code>script.js</code> together under <code>app/components</code>. Use <code>--dry-run</code> to preview files and <code>--force</code> only when you intentionally want to overwrite generated files.</p>
</section>

<section>
    <h2>Good Component Habits</h2>
    <ul>
        <li>Keep components focused on one piece of UI.</li>
        <li>Use constructor props for the data the component needs.</li>
        <li>Escape values in templates with <code>{{ }}</code>.</li>
        <li>Put component JavaScript behavior in its scoped <code>script.js</code>.</li>
        <li>Prefer components for repeated UI, not for every small HTML tag.</li>
    </ul>
</section>
HTML,
        ],
        'assets' => [
            'title' => 'Assets',
            'description' => 'Global assets, page assets, component assets, and public URL constants.',
            'content' => <<<'HTML'
<section>
    <h1>Assets</h1>
    <p>MicroPHP has app-scoped assets and standalone public assets. App-scoped page/component assets stay beside the page or component under <code>app</code>; standalone public assets such as favicons or vendor libraries live under <code>public/assets</code>.</p>
</section>

<section>
    <h2>Global Assets</h2>
    <p>Standalone public assets are configured in <code>config/assets.php</code> and loaded by the router for every frontend page.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

return [
    'css' => [
        '/assets/css/admin.css',
    ],
    'js' => [
        '/assets/js/app.js',
    ],
];
PHP)
            . <<<'HTML'
    <p>The default application also loads <code>app/assets/css/global.css</code> automatically when the file exists, exposed through the virtual <code>/assets/application/css/global.css</code> URL.</p>
</section>

<section>
    <h2>Page Assets</h2>
    <p>Put route-specific <code>style.css</code> and <code>script.js</code> in the route directory. The router builds the public URL from the matched page path and serves only allowlisted asset file types.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/posts/index.php
app/pages/posts/style.css
app/pages/posts/script.js
TEXT)
            . <<<'HTML'
</section>

<section>
    <h2>Component Assets</h2>
    <p>Components queue their own scoped assets when rendered through <code>View::component()</code> or <code>@component</code>.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/components/data-table/script.js
app/components/button/style.css
TEXT)
            . <<<'HTML'
    <p>Assets are emitted in deterministic scope order: global styles and scripts first, page assets second, and component assets last. This ordering is independent of when a component renders, allowing scoped component styles to override global defaults.</p>
</section>

<section>
    <h2>Asset Constants</h2>
    <table>
        <thead>
            <tr>
                <th>Constant</th>
                <th>Meaning</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>APP_ASSETS_PATH</code></td>
                <td>Filesystem path to application-owned global assets in <code>app/assets</code>.</td>
            </tr>
            <tr>
                <td><code>APP_ASSETS_URL</code></td>
                <td>Virtual URL prefix for application-owned global assets, normally <code>/assets/application</code>.</td>
            </tr>
            <tr>
                <td><code>PUBLIC_ASSETS_PATH</code></td>
                <td>Filesystem path to standalone public assets in <code>public/assets</code>.</td>
            </tr>
            <tr>
                <td><code>PUBLIC_ASSETS_URL</code></td>
                <td>Public URL prefix for standalone public assets, normally <code>/assets</code>.</td>
            </tr>
            <tr>
                <td><code>PAGES_URL</code></td>
                <td>Public URL prefix for page assets, normally <code>/assets/pages</code>.</td>
            </tr>
            <tr>
                <td><code>COMPONENTS_PATH</code></td>
                <td>Filesystem path to component folders in <code>app/components</code>.</td>
            </tr>
            <tr>
                <td><code>COMPONENTS_URL</code></td>
                <td>Public URL prefix for component styles and scripts, normally <code>/assets/components</code>.</td>
            </tr>
            <tr>
                <td><code>COMPONENT_ASSETS_PATH</code></td>
                <td>Backward-compatible alias of <code>COMPONENTS_PATH</code>.</td>
            </tr>
            <tr>
                <td><code>COMPONENT_ASSETS_URL</code></td>
                <td>Backward-compatible alias of <code>COMPONENTS_URL</code>.</td>
            </tr>
        </tbody>
    </table>
</section>
HTML,
        ],
        'forms-and-csrf' => [
            'title' => 'Forms, Sessions, and CSRF',
            'description' => 'Session startup, flash messages, redirects, CSRF fields, and explicit verification.',
            'content' => <<<'HTML'
<section>
    <h1>Forms, Sessions, and CSRF</h1>
    <p>MicroPHP configures sessions during bootstrap. When <code>var/sessions</code> exists or can be created, PHP stores session files there. Strict mode, cookie-only IDs, <code>HttpOnly</code>, and an explicit <code>SameSite</code> policy are enabled; <code>Secure</code> defaults on for HTTPS application URLs.</p>
</section>

<section>
    <h2>Session Usage</h2>
    <p>Because sessions are started during web bootstrap, pages, guards, and API handlers can use <code>$_SESSION</code> directly when needed.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$_SESSION['user'] = [
    'id' => 42,
    'role' => 'Admin',
];

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Flash Messages</h2>
    <p>Use <code>set_message()</code> to store a session-backed message. The default main layout calls <code>display_messages()</code> before page content.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

set_message('success', 'Profile saved.');
redirect('/profile');

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Redirects</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

redirect('/login');
redirect('/moved', 301);

?>
PHP)
            . <<<'HTML'
    <p>The helper sends a <code>Location</code> header and exits the current request.</p>
</section>

<section>
    <h2>CSRF Field in Templates</h2>
HTML
            . microphp_docs_code(<<<'HTMLCODE'
<form method="post">
    @csrf
    <label>
        Title
        <input name="title" required>
    </label>
    <button type="submit">Save</button>
</form>
HTMLCODE)
            . <<<'HTML'
    <p>The token is generated with <code>random_bytes()</code>, reused for the session, and escaped at render time. Frontend middleware enforces it for POST, PUT, PATCH, and DELETE.</p>
</section>

<section>
    <h2>CSRF Field in Plain PHP</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php use MicroPHP\View; ?>

<form method="post">
    <?= View::csrfField() ?>
    <button type="submit">Save</button>
</form>
PHP)
            . <<<'HTML'
    <p>Plain PHP pages do not need template directives. <code>View::csrfField()</code> emits the same escaped hidden field as <code>@csrf</code>.</p>
</section>

<section>
    <h2>Submit a Protected Page</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\Request;

if ($request->isMethod('POST')) {
    $title = $request->post('title');
    // The CSRF middleware has already validated _token or X-CSRF-Token.
}

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>API CSRF</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

// API writes require session CSRF by default.
// Only a strictly bearer-token API should set API_CSRF_ENABLED=false.
PHP)
            . <<<'HTML'
    <p>Tokens are accepted only from <code>_token</code> or <code>X-CSRF-Token</code>. <code>X-Requested-With</code> is not a CSRF token. CORS allows the CSRF header by default.</p>
</section>
HTML,
        ],
        'guards' => [
            'title' => 'Guards',
            'description' => 'Route guards with _guard.php files, inherited rules, redirects, forbidden responses, and overrides.',
            'content' => <<<'HTML'
<section>
    <h1>Guards</h1>
    <p>Guards protect frontend page directories. Place a <code>_guard.php</code> file in a page directory to run authorization logic before the page is rendered.</p>
    <p>Set <code>PAGE_ACCESS_MODE</code> to choose whether frontend pages use <code>_guard.php</code>, <code>_middleware.php</code>, or both. The default is <code>both</code>.</p>
</section>

<section>
    <h2>Choosing Guard or Middleware Mode</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
PAGE_ACCESS_MODE=guard       # only _guard.php files
PAGE_ACCESS_MODE=middleware  # only _middleware.php files
PAGE_ACCESS_MODE=both        # _guard.php first, then _middleware.php
TEXT)
            . <<<'HTML'
    <p>The default is <code>both</code>, so old guards still run while page middleware is part of the normal router path. Use <code>guard</code> for legacy-only behavior or <code>middleware</code> when a section has fully moved to middleware.</p>
</section>

<section>
    <h2>Guard Inheritance</h2>
    <p>The router looks for guards from the matched page directory up to the page root. Parent guards run before child guards. This lets you protect an entire section by placing one guard high in the tree.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
app/pages/admin/_guard.php
app/pages/admin/users/index.php
app/pages/admin/users/[userId]/index.php
TEXT)
            . <<<'HTML'
    <p>Both <code>/admin/users</code> and <code>/admin/users/15</code> inherit the <code>admin</code> guard.</p>
</section>

<section>
    <h2>Session-Based Guard</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

return auth_access([
    [
        'session_key' => 'user.id',
        'check' => '/^\d+$/',
        'on_fail' => 'You must be signed in.',
    ],
    [
        'session_key' => 'user.role',
        'check' => ['Admin', 'Editor'],
        'on_fail' => '/login',
    ],
]);
PHP)
            . <<<'HTML'
    <p><code>session_key</code> supports dot notation for nested session arrays. <code>check</code> can be a scalar value, an array of allowed values, or a regular expression string. If <code>on_fail</code> starts with <code>/</code>, MicroPHP redirects. Otherwise it renders a 403 response with that message.</p>
</section>

<section>
    <h2>Override Parent Guards</h2>
    <p>Pass <code>true</code> as the second argument to <code>auth_access()</code> to stop guard inheritance at the current directory.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

return auth_access([], true);
PHP)
            . <<<'HTML'
    <p>This is useful for public subpages inside a protected section, such as a status page or callback endpoint.</p>
</section>

<section>
    <h2>Custom Guard Handler</h2>
    <p>A guard file may also return the same shape manually: a callable <code>handler</code> and optional <code>override</code>.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

return [
    'override' => false,
    'handler' => function ($router, array $params): bool {
        if (($_SESSION['user']['role'] ?? null) !== 'Admin') {
            $router->serveForbidden('Administrators only.');
            return false;
        }

        return true;
    },
];
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Guard Habits</h2>
    <ul>
        <li>Place guards at the highest directory that shares the same access policy.</li>
        <li>Use redirects for authentication problems and 403 responses for authenticated users without enough permission.</li>
        <li>Keep guard logic small. Move complex checks into services and call those services from the guard.</li>
        <li>Do not rely on client-side hiding for access control. Guards run on the server before the page content is included.</li>
    </ul>
</section>
HTML,
        ],
        'http' => [
            'title' => 'HTTP Layer',
            'description' => 'Request and Response objects for testable HTTP-oriented code.',
            'content' => <<<'HTML'
<section>
    <h1>HTTP Layer</h1>
    <p>The <code>src/Http</code> directory contains dependency-free request and response objects. They are inspired by PSR-style HTTP objects but stay small enough to read in one sitting.</p>
</section>

<section>
    <h2>Request</h2>
    <p><code>MicroPHP\Http\Request</code> captures method, path, query parameters, POST data, server values, headers, raw body, and route parameters.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$request = $this->request();

$method = $request->method();
$path = $request->path();
$id = $request->input('id');
$token = $request->header('X-CSRF-TOKEN');
$json = $request->json();
$segments = $request->segments();

?>
PHP)
            . <<<'HTML'
    <p><code>input()</code> searches route parameters, POST data, query string, and decoded JSON body in that order.</p>
</section>

<section>
    <h2>Response</h2>
    <p><code>MicroPHP\Http\Response</code> lets handlers build responses without immediately sending headers or echoing output.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\Response;

$response = Response::json(['ok' => true])
    ->withHeader('X-App', 'MicroPHP')
    ->withStatus(201);

$response->send();
PHP)
            . <<<'HTML'
    <p>Available factories are <code>Response::html()</code>, <code>Response::text()</code>, <code>Response::json()</code>, <code>Response::noContent()</code>, and <code>Response::redirect()</code>.</p>
</section>

<section>
    <h2>Why Use These Objects</h2>
    <p>Classic pages remain plain PHP, but request data should come from the injected <code>Request</code> object and responses should use <code>Response</code>. This keeps behavior immutable, testable, and compatible with middleware and persistent workers.</p>
</section>
HTML,
        ],
        'middleware' => [
            'title' => 'Middleware',
            'description' => 'MiddlewareInterface, MiddlewarePipeline, LoggingMiddleware, CorsMiddleware, and practical usage.',
            'content' => <<<'HTML'
<section>
    <h1>Middleware</h1>
    <p>Middleware is a request pipeline around a handler. It is useful when several handlers should share behavior such as logging, CORS headers, authentication, rate limiting, or response decoration.</p>
</section>

<section>
    <h2>Hono-Style Mental Model</h2>
    <p>If a route or page is the final controller action, middleware is the code that runs around that action. Like Hono middleware, it receives the request context, decides whether to continue, and can modify or replace the response.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

return page_middleware(function (Request $request, callable $next): Response {
    // before controller/page
    $response = $next($request);
    // after controller/page

    return $response->withHeader('X-Handled-By', 'MicroPHP');
});
PHP)
            . <<<'HTML'
    <p>Calling <code>$next($request)</code> is the same idea as <code>await next()</code> in Hono. Returning a response before calling <code>$next</code> stops the request early.</p>
</section>

<section>
    <h2>Contract</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

namespace MicroPHP\Http;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
PHP)
            . <<<'HTML'
    <p>A middleware can call <code>$next($request)</code> to continue the chain or return its own <code>Response</code> to stop processing.</p>
</section>

<section>
    <h2>Pipeline</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\MiddlewarePipeline;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;
use MicroPHP\Http\Middleware\LoggingMiddleware;

$request = Request::fromGlobals();

$pipeline = (new MiddlewarePipeline())
    ->pipe(new LoggingMiddleware(app(MicroPHP\Logger::class)));

$response = $pipeline->handle($request, function (Request $request): Response {
    return Response::html('<h1>Hello</h1>');
});

$response->send();
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Page Router Middleware</h2>
    <p>When <code>PAGE_ACCESS_MODE</code> is <code>middleware</code> or <code>both</code>, the page router looks for inherited <code>_middleware.php</code> files from the matched page directory up to the page root. Parent middleware runs before child middleware.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\Middleware\LoggingMiddleware;

return page_middleware([
    new LoggingMiddleware(app(MicroPHP\Logger::class)),
]);
PHP)
            . <<<'HTML'
    <p>A middleware file can return one middleware, a list, or <code>page_middleware([...], true)</code> to stop inheritance from parent directories.</p>
</section>

<section>
    <h2>Auth Middleware</h2>
    <p><code>auth_middleware()</code> uses the same session-rule format as <code>auth_access()</code>, but returns middleware for <code>_middleware.php</code>.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

return auth_middleware([
    [
        'session_key' => 'user.role',
        'check' => ['Admin'],
        'on_fail' => '/login',
    ],
]);
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Built-In Examples</h2>
    <table>
        <thead>
            <tr>
                <th>Middleware</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>LoggingMiddleware</code></td>
                <td>Logs method, path, status, and duration through <code>MicroPHP\Logger</code>.</td>
            </tr>
            <tr>
                <td><code>CorsMiddleware</code></td>
                <td>Adds CORS headers from an explicit allowlist and handles <code>OPTIONS</code> requests.</td>
            </tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Guard Compatibility</h2>
    <p><code>_guard.php</code> files still work through a deprecated middleware adapter. Migrate their authorization callbacks to <code>_middleware.php</code>; there is one middleware execution pipeline.</p>
</section>
HTML,
        ],
        'api' => [
            'title' => 'API',
            'description' => 'Versioned API routes, route methods, parameters, JSON requests, CORS, and internal API calls.',
            'content' => <<<'HTML'
<section>
    <h1>API</h1>
    <p>Requests whose path starts with <code>/api/</code> are handled by <code>MicroPHP\Api</code> when <code>API_SERVICE_ENABLED</code> is true.</p>
</section>

<section>
    <h2>Versioned Route Files</h2>
    <p>The segment after <code>/api</code> is the API version. Filesystem API route handlers live under <code>app/api/&lt;version&gt;</code>, with one uppercase HTTP method file per route directory.</p>
HTML
            . microphp_docs_code(<<<'TEXT'
/api/v1/posts
app/api/v1/posts/GET.php
app/api/v1/posts/POST.php

/api/v1/posts/15
app/api/v1/posts/[id]/GET.php
app/api/v1/posts/[id]/PATCH.php
app/api/v1/posts/[id]/DELETE.php
TEXT)
            . <<<'HTML'
</section>

<section>
    <h2>Define Routes</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

return function (Request $request): Response {
    $data = $request->json() ?? $request->post();
    $id = Database::insert('posts', [
        'title' => $data['title'] ?? '',
        'content' => $data['content'] ?? '',
        'created_at' => date('c'),
    ]);

    return Response::json(['data' => ['id' => $id]], 201);
};
PHP)
            . <<<'HTML'
    <p>Supported method files are <code>GET.php</code>, <code>POST.php</code>, <code>PUT.php</code>, <code>PATCH.php</code>, <code>DELETE.php</code>, <code>HEAD.php</code>, and <code>OPTIONS.php</code>. Dynamic API directories use the <code>[name]</code> syntax.</p>
</section>

<section>
    <h2>JSON Request Bodies</h2>
    <p>For <code>POST</code>, <code>PUT</code>, and <code>PATCH</code>, read JSON with <code>$request-&gt;json()</code> or form data from <code>$request-&gt;post()</code>. Legacy handlers using <code>function (array $params, ?array $data)</code> still receive decoded JSON as the second argument. Invalid JSON produces an error response.</p>
</section>

<section>
    <h2>Status Codes</h2>
    <p>Filesystem API handlers return <code>Response</code> objects directly, so choose status codes with helpers such as <code>Response::json($data, 201)</code> or <code>Response::noContent()</code>. Legacy <code>Api::get()</code> style routes still normalize array responses for compatibility.</p>
</section>

<section>
    <h2>Legacy Route CORS</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Api;
use MicroPHP\Database;
use MicroPHP\Http\Request;

Api::get('/posts/:post_id/comments', function (Request $request): array {
    return Database::table('comments')
        ->where(['post_id' => $request->route('post_id')])
        ->get();
}, [
    'allowed_origins' => ['*'],
]);
PHP)
            . <<<'HTML'
    <p>The route CORS array is available on legacy <code>Api::get()</code> style routes while applications migrate to filesystem method files.</p>
</section>

<section>
    <h2>API Middleware</h2>
    <p>The API router always dispatches through <code>MiddlewarePipeline</code>. CORS is the first built-in middleware, then global API middleware and inherited <code>_middleware.php</code> files.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

return api_middleware(function (Request $request, callable $next): Response {
    return $next($request)->withHeader('X-Api-Version', 'v1');
});
PHP)
            . <<<'HTML'
    <p>Place this in <code>app/api/_middleware.php</code>, a version folder such as <code>app/api/v1/_middleware.php</code>, or a route folder. Middleware can return a <code>Response</code> early or decorate the response returned by the handler.</p>
</section>

<section>
    <h2>Call API Logic Internally</h2>
    <p>Internal API dispatch is available for tests or for deliberately reusing route behavior. Normal server-rendered pages should use application services or <code>Database</code> directly instead of treating API routes as a database layer.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Api;

$posts = Api::makeRequest('GET', '/v1/posts');

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Generate an API Handler</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php bin/create-api.php /api/v1/users
php bin/create-api.php /api/v1/users/:id
php bin/create-api.php /users --version=v2
php bin/create-api.php /users --dry-run
TEXT)
            . <<<'HTML'
    <p>The generator creates a route directory with <code>GET.php</code>, <code>POST.php</code>, <code>PUT.php</code>, <code>PATCH.php</code>, and <code>DELETE.php</code> handlers for the chosen path. <code>HEAD</code> and <code>OPTIONS</code> are handled centrally unless explicit method files are added.</p>
</section>
HTML,
        ],
        'database' => [
            'title' => 'Database',
            'description' => 'Database configuration, PDO access, MongoDB collections, drivers, and raw queries.',
            'content' => <<<'HTML'
<section>
    <h1>Database</h1>
    <p>MicroPHP uses a shared <code>MicroPHP\Database</code> instance. SQL databases use PDO; SQLite is the default driver, and MySQL, MariaDB, PostgreSQL, and SQL Server are also supported. MongoDB is available through the collection API.</p>
</section>

<section>
    <h2>Configuration</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
DB_DRIVER=sqlite
DB_PATH=database/library.db

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=microphp
DB_USER=root
DB_PASS=secret

DB_DRIVER=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_NAME=microphp
DB_USER=root
DB_PASS=secret

DB_DRIVER=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=microphp
DB_USER=postgres
DB_PASS=secret

DB_DRIVER=sqlsrv
DB_HOST=localhost
DB_PORT=1433
DB_NAME=microphp
DB_USER=sa
DB_PASS=secret

DB_DRIVER=mongodb
DB_HOST=localhost
DB_PORT=27017
DB_NAME=microphp
DB_USER=
DB_PASS=
DB_AUTH_SOURCE=admin

# Optional override for custom PDO DSNs, SQL Server instances,
# MongoDB replica sets, or mongodb+srv:// clusters.
DB_DSN=
TEXT)
            . <<<'HTML'
    <p>These values are read in <code>config/app.php</code>. <code>DB_DSN</code> overrides the generated DSN/URI when set. Unsupported driver names fail clearly when the database connection is created.</p>
</section>

<section>
    <h2>Raw Queries</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$db = Database::getInstance();

$rows = $db->query('SELECT * FROM posts WHERE id = ?', [$id]);
$first = is_array($rows) ? ($rows[0] ?? null) : null;
$all = $db->query('SELECT * FROM posts ORDER BY created_at DESC');
$error = $db->getError();

?>
PHP)
            . <<<'HTML'
    <p><code>query()</code> returns rows for <code>SELECT</code> queries, <code>true</code> for successful write queries, and <code>false</code> on database errors.</p>
</section>

<section>
    <h2>MongoDB Collections</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$posts = Database::collection('posts');

$id = $posts->insert([
    'title' => $title,
    'content' => $content,
    'created_at' => date('c'),
]);

$recent = $posts->find(['published' => true], [
    'sort' => ['created_at' => -1],
    'limit' => 10,
]);

$first = $posts->first(['slug' => $slug]);
$updated = $posts->update(['slug' => $slug], ['title' => $newTitle]);
$deleted = $posts->delete(['slug' => $slug], ['limit' => 1]);
$error = $posts->getError();

?>
PHP)
            . <<<'HTML'
    <p>MongoDB is not SQL based, so <code>query()</code> and <code>table()</code> remain SQL-only. Collection results are normalized to arrays so API handlers can return them as JSON.</p>
</section>

<section>
    <h2>Database Class</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$db = Database::getInstance();
$rows = $db->query('SELECT * FROM posts WHERE id = :id', [
    ':id' => $id,
]);

$affected = $db->execute('UPDATE posts SET title = :title WHERE id = :id', [
    ':title' => $title,
    ':id' => $id,
]);

$lastId = $db->lastInsertId();

?>
PHP)
            . <<<'HTML'
    <p>Use <code>execute()</code> when you need the affected row count for write operations.</p>
</section>

<section>
    <h2>Safety</h2>
    <p>Values passed as query parameters are bound through PDO prepared statements. Do not concatenate user input into SQL strings. When using raw SQL, keep table and column names controlled by your code.</p>
</section>
HTML,
        ],
        'query-builder' => [
            'title' => 'Query Builder',
            'description' => 'Readable database operations without hand-writing common SQL.',
            'content' => <<<'HTML'
<section>
    <h1>Query Builder</h1>
    <p>The query builder is the everyday database API in MicroPHP. It is for developers who want to work with tables and conditions without learning SQL first. Under the hood, it still uses PDO prepared statements for values.</p>
</section>

<section>
    <h2>Read Rows</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$posts = Database::table('posts')
    ->select(['id', 'title', 'created_at'])
    ->where(['published' => 1])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>First Row</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$post = Database::table('posts')
    ->where(['id' => $id])
    ->first();

?>
PHP)
            . <<<'HTML'
    <p><code>first()</code> returns the first row or <code>null</code>.</p>
</section>

<section>
    <h2>Operators</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$recent = Database::table('posts')
    ->where(['created_at' => ['>=', $since]])
    ->get();

?>
PHP)
            . <<<'HTML'
    <p>Plain values use <code>=</code>. For other operators, pass <code>[operator, value]</code>.</p>
</section>

<section>
    <h2>Count</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$total = Database::table('posts')
    ->where(['published' => 1])
    ->count();

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Joins</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$loans = \MicroPHP\Database::join(
    'wypozyczenie',
    'uzytkownik',
    'wypozyczenie.id_uzytkownik',
    'uzytkownik.id'
)
    ->join('ksiazka', 'wypozyczenie.id_ksiazka', '=', 'ksiazka.id')
    ->select([
        'wypozyczenie.id',
        'uzytkownik.imie',
        'uzytkownik.nazwisko',
        'ksiazka.tytul',
    ])
    ->get();

?>
PHP)
            . <<<'HTML'
    <p>Join types can be <code>INNER</code>, <code>LEFT</code>, <code>RIGHT</code>, or <code>FULL</code>. Join column expressions must be safe <code>table.column</code> strings.</p>
</section>

<section>
    <h2>Insert, Update, Delete</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Database;

$id = Database::insert('posts', [
    'title' => $title,
    'content' => $content,
    'created_at' => date('c'),
]);

$updated = Database::update('posts', [
    'title' => $newTitle,
], [
    'id' => $id,
]);

$deleted = Database::delete('posts', [
    'id' => $id,
]);

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Safety Boundary</h2>
    <p>The builder binds values safely and validates table names, column names, operators, join columns, and order directions. Keep identifiers defined by your code, and treat invalid identifier exceptions as rejected input rather than something to escape manually.</p>
    <p><code>Database::update()</code> and <code>Database::delete()</code> require at least one condition to prevent accidental mass writes. Use raw SQL deliberately if a maintenance script truly needs to affect every row.</p>
</section>
HTML,
        ],
        'container' => [
            'title' => 'Container',
            'description' => 'The app() helper, service bindings, singletons, instances, and constructor autowiring.',
            'content' => <<<'HTML'
<section>
    <h1>Container</h1>
    <p>MicroPHP includes a small dependency injection container. It is created lazily by the global <code>app()</code> helper in <code>bootstrap/app.php</code>.</p>
</section>

<section>
    <h2>Resolve Services</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$container = app();
$logger = app(MicroPHP\Logger::class);

?>
PHP)
            . <<<'HTML'
    <p>Calling <code>app()</code> with no argument returns the shared container. Calling it with a class name resolves that class.</p>
</section>

<section>
    <h2>Default Bindings</h2>
    <p>The bootstrap file registers <code>MicroPHP\Logger</code> and <code>MicroPHP\Database</code> as singletons.</p>
</section>

<section>
    <h2>Bind a Service</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

app()->singleton(App\Services\ReportService::class, function () {
    return new App\Services\ReportService(
        app(MicroPHP\Logger::class)
    );
});

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Autowiring</h2>
    <p>The container reflects constructor parameters. Typed, non-builtin class dependencies are resolved automatically.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

class ReportController
{
    public function __construct(
        private MicroPHP\Logger $logger,
    ) {}
}

$controller = app()->make(ReportController::class);

?>
PHP)
            . <<<'HTML'
    <p>Scalar values and builtin types must have defaults or be supplied explicitly.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$service = app()->make(App\Services\Mailer::class, [
    'fromAddress' => 'hello@example.com',
]);

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Methods</h2>
    <table>
        <thead>
            <tr>
                <th>Method</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>bind($abstract, $concrete)</code></td><td>Resolve a fresh instance each time.</td></tr>
            <tr><td><code>singleton($abstract, $concrete)</code></td><td>Resolve once and reuse the object.</td></tr>
            <tr><td><code>instance($abstract, $object)</code></td><td>Register an already-built object.</td></tr>
            <tr><td><code>has($abstract)</code></td><td>Check if a class, binding, or instance can be resolved.</td></tr>
            <tr><td><code>make($abstract, $parameters)</code></td><td>Create or resolve an object.</td></tr>
        </tbody>
    </table>
</section>
HTML,
        ],
        'errors-and-logging' => [
            'title' => 'Errors and Logging',
            'description' => 'APP_DEBUG, centralized exception handling, file logging, and request logging middleware.',
            'content' => <<<'HTML'
<section>
    <h1>Errors and Logging</h1>
    <p>MicroPHP centralizes uncaught exceptions and PHP errors in <code>MicroPHP\ExceptionHandler</code>. The handler logs every uncaught problem and controls what is shown to visitors through <code>APP_DEBUG</code>.</p>
</section>

<section>
    <h2>Debug Mode</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
APP_ENV=local
APP_DEBUG=true
TEXT)
            . <<<'HTML'
    <p>When <code>APP_DEBUG=true</code>, sanitized exception class and location diagnostics may be shown. Messages and traces remain in the log. If <code>.env</code> is absent, debug defaults to false and users receive a generic 500 response.</p>
</section>

<section>
    <h2>Application Log</h2>
    <p>The default logger writes to <code>var/log/app.log</code>. The bootstrap file binds <code>MicroPHP\Logger</code> as a singleton. Every event is one JSON object per line, so control characters in messages remain encoded instead of creating forged entries.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

$logger = app(MicroPHP\Logger::class);

$logger->info('profile updated', ['user_id' => $id]);
$logger->warning('slow query', ['duration_ms' => 740]);
$logger->error('payment failed', ['order_id' => $orderId]);

?>
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Log Levels</h2>
    <p>The logger provides <code>emergency()</code>, <code>error()</code>, <code>warning()</code>, <code>info()</code>, and <code>debug()</code>. Each method accepts a message and optional context array; the complete event is stored as structured JSON.</p>
</section>

<section>
    <h2>Request Logging Middleware</h2>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Http\Middleware\LoggingMiddleware;
use MicroPHP\Http\MiddlewarePipeline;

$pipeline = (new MiddlewarePipeline())
    ->pipe(new LoggingMiddleware(app(MicroPHP\Logger::class)));

?>
PHP)
            . <<<'HTML'
    <p>This middleware logs the request method, path, response status, and duration in milliseconds for handlers passed through the pipeline.</p>
</section>

<section>
    <h2>Production Habit</h2>
    <p>Keep <code>APP_DEBUG=false</code> in production, make sure <code>var/log</code> is writable by the PHP process, and review logs during deployment or after 500 responses.</p>
</section>
HTML,
        ],
        'configuration' => [
            'title' => 'Configuration',
            'description' => 'Core constants, environment variables, database settings, application paths, assets, and cache.',
            'content' => <<<'HTML'
<section>
    <h1>Configuration</h1>
    <p>MicroPHP configuration is currently constant-based. <code>config/app.php</code> reads <code>.env</code> with <code>parse_ini_file()</code>, then defines constants used by the framework and application.</p>
</section>

<section>
    <h2>Application Settings</h2>
    <table>
        <thead>
            <tr>
                <th>Constant</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>APP_ENV</code></td><td>Current environment name, defaulting to <code>production</code> when no environment file exists.</td></tr>
            <tr><td><code>APP_DEBUG</code></td><td>Controls detailed error output.</td></tr>
            <tr><td><code>APP_URL</code></td><td>Trusted absolute application URL used instead of the request Host header.</td></tr>
            <tr><td><code>PROJECT_NAME</code></td><td>Default page title used when no page-specific title is set.</td></tr>
            <tr><td><code>API_SERVICE_ENABLED</code></td><td>Enables or disables handling of <code>/api/</code> requests.</td></tr>
            <tr><td><code>API_CSRF_ENABLED</code></td><td>Protects API write requests by default; disable only for strictly bearer-token APIs.</td></tr>
            <tr><td><code>SESSION_COOKIE_SECURE</code></td><td>Restricts the session cookie to HTTPS; defaults from <code>APP_URL</code>.</td></tr>
            <tr><td><code>SESSION_COOKIE_SAMESITE</code></td><td>Session cookie policy: <code>Lax</code>, <code>Strict</code>, or secure <code>None</code>.</td></tr>
            <tr><td><code>SECURITY_HEADERS_ENABLED</code></td><td>Enables central browser security response headers.</td></tr>
            <tr><td><code>HSTS_ENABLED</code></td><td>Adds HSTS on secure requests; defaults from <code>APP_URL</code>.</td></tr>
            <tr><td><code>CONTENT_SECURITY_POLICY</code></td><td>Application CSP; update its source allowlists when adding external assets.</td></tr>
            <tr><td><code>PAGE_ACCESS_MODE</code></td><td>Chooses frontend page protection: <code>guard</code>, <code>middleware</code>, or <code>both</code>.</td></tr>
            <tr><td><code>FRONTEND_MIDDLEWARE</code></td><td>Global middleware entries for the frontend router.</td></tr>
            <tr><td><code>API_MIDDLEWARE</code></td><td>Global middleware entries for the API router.</td></tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Path Settings</h2>
    <table>
        <thead>
            <tr>
                <th>Constant</th>
                <th>Default</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>APP_PATH</code></td><td><code>ROOT_PATH . '/app'</code></td></tr>
            <tr><td><code>PUBLIC_PATH</code></td><td><code>ROOT_PATH . '/public'</code></td></tr>
            <tr><td><code>PAGES_PATH</code></td><td><code>APP_PATH . '/pages'</code></td></tr>
            <tr><td><code>LAYOUTS_PATH</code></td><td><code>APP_PATH . '/layouts'</code></td></tr>
            <tr><td><code>API_ROUTES_PATH</code></td><td><code>APP_PATH . '/api'</code></td></tr>
            <tr><td><code>APP_ASSETS_PATH</code></td><td><code>APP_PATH . '/assets'</code></td></tr>
            <tr><td><code>APP_ASSETS_URL</code></td><td><code>ASSETS_URL . '/application'</code></td></tr>
            <tr><td><code>PUBLIC_ASSETS_PATH</code></td><td><code>PUBLIC_PATH . '/assets'</code></td></tr>
            <tr><td><code>PUBLIC_ASSETS_URL</code></td><td><code>ASSETS_URL</code></td></tr>
            <tr><td><code>VIEW_CACHE_PATH</code></td><td><code>ROOT_PATH . '/var/cache/views'</code></td></tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Database Settings</h2>
    <table>
        <thead>
            <tr>
                <th>Constant</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>DB_DRIVER</code></td><td><code>sqlite</code>, <code>mysql</code>, <code>mariadb</code>, <code>pgsql</code>, <code>sqlsrv</code>, or <code>mongodb</code>. Aliases include <code>postgres</code>, <code>sqlserver</code>, <code>mssql</code>, and <code>mongo</code>.</td></tr>
            <tr><td><code>DB_DSN</code></td><td>Optional explicit PDO DSN or MongoDB URI. When set, it overrides generated connection strings.</td></tr>
            <tr><td><code>DB_PATH</code></td><td>SQLite database file path. Relative paths resolve from <code>ROOT_PATH</code>.</td></tr>
            <tr><td><code>DB_PERSISTENT</code></td><td>Enable persistent PDO connections deliberately. Defaults to <code>false</code>.</td></tr>
            <tr><td><code>DB_HOST</code></td><td>Host for MariaDB, MySQL, PostgreSQL, SQL Server, or MongoDB.</td></tr>
            <tr><td><code>DB_PORT</code></td><td>Optional port for generated network database connection strings.</td></tr>
            <tr><td><code>DB_NAME</code></td><td>SQL database name or MongoDB database name.</td></tr>
            <tr><td><code>DB_USER</code></td><td>Database user.</td></tr>
            <tr><td><code>DB_PASS</code></td><td>Database password.</td></tr>
            <tr><td><code>DB_AUTH_SOURCE</code></td><td>Optional MongoDB authentication database.</td></tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Cache Trust</h2>
    <p><code>VIEW_CACHE_TRUST</code> controls how compiled <code>.micro.php</code> views are loaded. Keep it false while developing. In production, set it true only when deployment warms the view cache first.</p>
</section>
HTML,
        ],
        'cli-and-cache' => [
            'title' => 'CLI and View Cache',
            'description' => 'Command-line tools for cache management, component generation, and API handler generation.',
            'content' => <<<'HTML'
<section>
    <h1>CLI and View Cache</h1>
    <p>MicroPHP ships small CLI scripts in <code>bin/</code>. Composer exposes them as package binaries, and they can also be run directly with PHP.</p>
</section>

<section>
    <h2>View Cache</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php bin/view-cache.php warm
php bin/view-cache.php clear
php bin/view-cache.php stats
TEXT)
            . <<<'HTML'
    <table>
        <thead>
            <tr>
                <th>Command</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>warm</code></td><td>Compile all page and component <code>.micro.php</code> templates.</td></tr>
            <tr><td><code>clear</code></td><td>Delete compiled view cache files.</td></tr>
            <tr><td><code>stats</code></td><td>Print cache file count, total size, and oldest/newest timestamps.</td></tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Component Generator</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php bin/create-component.php AlertBox
php bin/create-component.php Forms/Input
php bin/create-component.php theme-change --force
php bin/create-component.php AlertBox --dry-run
TEXT)
            . <<<'HTML'
    <p>The generator validates names and creates the PHP class, template, stylesheet, and script together under <code>app/components/&lt;component-name&gt;</code>.</p>
</section>

<section>
    <h2>API Generator</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php bin/create-api.php /api/v1/users
php bin/create-api.php /api/v1/users/:id
php bin/create-api.php /users --version=v1
php bin/create-api.php /users --dry-run
TEXT)
            . <<<'HTML'
    <p>The generator creates a filesystem route directory with method files and detects conflicts in existing API route files. It refuses to overwrite existing method files unless <code>--force</code> is used.</p>
</section>

<section>
    <h2>Deployment Cache Flow</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
composer install --no-dev --optimize-autoloader
php bin/view-cache.php warm
TEXT)
            . <<<'HTML'
    <p>After warming the cache, set <code>VIEW_CACHE_TRUST=true</code> for production to skip template freshness checks.</p>
</section>
HTML,
        ],
        'testing' => [
            'title' => 'Testing',
            'description' => 'PHPUnit, isolated database transactions, API testing, and view cache checks.',
            'content' => <<<'HTML'
<section>
    <h1>Testing</h1>
    <p><code>composer test</code> runs the canonical PHPUnit suite. It covers Request, Response, routing, middleware, APIs, templates, CSRF, assets, and database behavior without requiring a real web request.</p>
</section>

<section>
    <h2>Optional Smoke Test</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php tests/manual-smoke-test.php
TEXT)
            . <<<'HTML'
    <p>The historical smoke script remains an optional developer tool. Use <code>composer test</code> for acceptance and CI.</p>
</section>

<section>
    <h2>View Cache Test</h2>
HTML
            . microphp_docs_code(<<<'TEXT'
php bin/view-cache.php warm
php bin/view-cache.php stats
TEXT)
            . <<<'HTML'
    <p>Run the cache warm command before production deployment and after changing template compiler behavior.</p>
</section>

<section>
    <h2>API Handler Tests</h2>
    <p>You can test API route logic without HTTP by calling <code>Api::makeRequest()</code>.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

use MicroPHP\Api;

$created = Api::makeRequest('POST', '/v1/posts', [
    'title' => 'Test post',
    'content' => 'Created by a test.',
]);

assert(isset($created['id']));
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>Database Tests</h2>
    <p>Automated tests create a temporary SQLite database and install it with <code>Database::usePdo()</code>. They never run against <code>database/library.db</code>. Use <code>Database::transaction()</code> for commit and rollback behavior.</p>
HTML
            . microphp_docs_code(<<<'PHP'
<?php

\MicroPHP\Database::transaction(function (): void {
    $id = \MicroPHP\Database::insert('posts', [
        'title' => 'Test',
        'content' => 'Body',
        'created_at' => date('c'),
    ]);

    assert(is_int($id));
});
PHP)
            . <<<'HTML'
</section>

<section>
    <h2>PHPUnit Suite</h2>
    <p>The project uses PHPUnit 10.5 for PHP 8.1 compatibility. Dedicated unit and integration cases live under <code>tests/Unit</code> and <code>tests/Integration</code>, with shared setup in <code>tests/bootstrap.php</code>.</p>
</section>

<section>
    <h2>What to Test First</h2>
    <ul>
        <li>Guards for protected routes.</li>
        <li>API handlers for validation and status behavior.</li>
        <li>Database writes inside transactions.</li>
        <li>Template directives when extending the compiler.</li>
        <li>Component rendering when props or assets change.</li>
    </ul>
</section>
HTML,
        ],
        'composer' => [
            'title' => 'Composer Readiness',
            'description' => 'Current Composer validation status and requirements for create-project distribution.',
            'content' => <<<'HTML'
<section>
    <h1>Composer Readiness</h1>
    <p>The current MicroPHP package is structurally ready to be used as a Composer project skeleton. The manifest is valid, autoloading is coherent, and the platform requirement is satisfied in the checked environment.</p>
</section>

<section>
    <h2>Current Checks</h2>
    <table>
        <thead>
            <tr>
                <th>Check</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>composer --version</code></td>
                <td>Composer 2.10.2 with PHP 8.2.12 was available in the local environment.</td>
            </tr>
            <tr>
                <td><code>composer validate --strict</code></td>
                <td>Run before tagging to verify the package metadata, lock file, and publish readiness.</td>
            </tr>
            <tr>
                <td><code>composer dump-autoload --dry-run --optimize --strict-psr</code></td>
                <td>Run before tagging to verify PSR-4 autoloading for framework and application classes.</td>
            </tr>
            <tr>
                <td><code>composer check-platform-reqs</code></td>
                <td>PHP and required extensions from the lock file passed.</td>
            </tr>
            <tr>
                <td><code>composer install --dry-run --no-interaction</code></td>
                <td>Run when network access is available to verify a clean install from the lock file.</td>
            </tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Manifest Shape</h2>
HTML
            . microphp_docs_code(<<<'JSON'
{
    "name": "yacho/microphp",
    "description": "MicroPHP framework application skeleton.",
    "type": "project",
    "license": "MIT",
    "keywords": [
        "microphp",
        "framework",
        "filesystem-routing",
        "project-skeleton"
    ],
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5 || ^11.0"
    },
    "autoload": {
        "psr-4": {
            "MicroPHP\\": "src/",
            "App\\": "app/"
        },
        "files": [
            "src/Helpers.php"
        ],
        "exclude-from-classmap": [
            "/app/components/"
        ]
    },
    "bin": [
        "bin/view-cache.php",
        "bin/create-component.php",
        "bin/create-api.php"
    ],
    "scripts": {
        "post-create-project-cmd": [
            "@php bin/setup-project.php"
        ],
        "microphp:setup": "@php bin/setup-project.php",
        "test": "@php tests/manual-smoke-test.php"
    }
}
JSON)
            . <<<'HTML'
    <p><code>type: project</code> is appropriate for an application skeleton. The package has a valid vendor/package name, a PHP requirement, PSR-4 autoloading, autoloaded helper functions, CLI binaries, and a setup script for new projects.</p>
</section>

<section>
    <h2>Using create-project</h2>
    <p>Composer can create a new project from an existing package:</p>
HTML
            . microphp_docs_code(<<<'TEXT'
composer create-project yacho/microphp my-app
TEXT)
            . <<<'HTML'
    <p>For this command to work for other developers, Composer must be able to find the package. Publish the repository to Packagist or document a VCS/path repository setup for private installs.</p>
</section>

<section>
    <h2>Distribution Notes</h2>
    <ul>
        <li>Keep <code>vendor/</code>, <code>.env</code>, and runtime cache/log/session files out of Git.</li>
        <li>Use <code>.env.example</code> as the committed configuration template.</li>
        <li>Use the <code>App\</code> namespace for application-owned classes under <code>app/</code>.</li>
        <li>Use <code>composer run microphp:setup</code> to rebuild local project configuration.</li>
    </ul>
    <p>After validation passes, tag the repository and submit the public Git URL to Packagist.</p>
</section>

<section>
    <h2>Reference</h2>
    <p>Composer documents <a href="https://getcomposer.org/doc/03-cli.md#create-project">create-project</a> as a command for creating a new project from an existing package, and its <a href="https://getcomposer.org/doc/04-schema.md">composer.json schema</a> defines fields such as <code>type</code>, <code>autoload</code>, and <code>bin</code>.</p>
</section>
HTML,
        ],
        'best-practices' => [
            'title' => 'Best Practices',
            'description' => 'Practical habits for keeping a MicroPHP project clear, safe, and maintainable.',
            'content' => <<<'HTML'
<section>
    <h1>Best Practices</h1>
    <p>MicroPHP is intentionally close to PHP. That makes discipline visible. The following habits keep an application easy to understand as it grows.</p>
</section>

<section>
    <h2>Keep Routes Obvious</h2>
    <ul>
        <li>Let directory names match the URL you want users and developers to see.</li>
        <li>Use dynamic folders such as <code>[userId]</code> only where the segment is truly a parameter.</li>
        <li>Keep route-specific assets beside the page they belong to.</li>
        <li>Use one guard high in a route tree instead of repeating checks in every child page.</li>
    </ul>
</section>

<section>
    <h2>Choose the Right View Style</h2>
    <ul>
        <li>Use classic <code>index.php</code> pages when request handling and markup are simple and direct.</li>
        <li>Use <code>index.micro.php</code> when a page benefits from template directives, escaped echo, components, includes, and CSRF form fields.</li>
        <li>Keep complex data loading outside templates when it starts to obscure the markup.</li>
    </ul>
</section>

<section>
    <h2>Prefer Safe Output</h2>
    <ul>
        <li>Use <code>{{ }}</code> in templates for escaped values.</li>
        <li>Use <code>htmlspecialchars()</code> in classic PHP pages.</li>
        <li>Use raw output only for trusted HTML you produced or sanitized.</li>
    </ul>
</section>

<section>
    <h2>Use the Query Builder for Common Work</h2>
    <p>Reach for <code>Database::table()</code>, <code>Database::insert()</code>, <code>Database::update()</code>, and <code>Database::delete()</code> before writing SQL by hand. Use raw SQL when the query is genuinely beyond the builder's current scope.</p>
</section>

<section>
    <h2>Keep Core and App Changes Separate</h2>
    <p>Application features belong in <code>app</code>, component folders, guards, API handlers, middleware, and services. Change framework internals in <code>src</code> only when the framework behavior itself needs to evolve, and cover those changes with tests.</p>
</section>

<section>
    <h2>Deploy Deliberately</h2>
    <ul>
        <li>Use <code>APP_DEBUG=false</code> in production.</li>
        <li>Run <code>composer install --no-dev --optimize-autoloader</code>.</li>
        <li>Warm views with <code>php bin/view-cache.php warm</code>.</li>
        <li>Use <code>VIEW_CACHE_TRUST=true</code> only after the cache is warmed.</li>
        <li>Make <code>var/cache</code>, <code>var/log</code>, and <code>var/sessions</code> writable by PHP.</li>
    </ul>
</section>

<section>
    <h2>Grow the Framework Carefully</h2>
    <p>Natural future additions include validation, route caching, a console kernel, and a fuller PHPUnit suite. Add these as cohesive framework features, not as one-off page code.</p>
</section>
HTML,
        ],
    ];

    return $pages;
}

function microphp_docs_navigation(string $currentSlug): string
{
    $html = '<nav aria-label="Documentation sections"><h2>Documentation</h2><ol>';

    foreach (microphp_docs_pages() as $slug => $page) {
        $href = htmlspecialchars(microphp_docs_url($slug), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8');
        $current = $slug === $currentSlug ? ' aria-current="page"' : '';
        $html .= '<li' . $current . '><a href="' . $href . '">' . $title . '</a></li>';
    }

    return $html . '</ol></nav>';
}

function microphp_docs_pagination(string $currentSlug): string
{
    $slugs = array_keys(microphp_docs_pages());
    $index = array_search($currentSlug, $slugs, true);

    if ($index === false) {
        return '';
    }

    $links = [];

    if (isset($slugs[$index - 1])) {
        $previous = microphp_docs_pages()[$slugs[$index - 1]];
        $links[] = '<a href="' . htmlspecialchars(microphp_docs_url($slugs[$index - 1]), ENT_QUOTES, 'UTF-8') . '">Previous: ' . htmlspecialchars($previous['title'], ENT_QUOTES, 'UTF-8') . '</a>';
    }

    if (isset($slugs[$index + 1])) {
        $next = microphp_docs_pages()[$slugs[$index + 1]];
        $links[] = '<a href="' . htmlspecialchars(microphp_docs_url($slugs[$index + 1]), ENT_QUOTES, 'UTF-8') . '">Next: ' . htmlspecialchars($next['title'], ENT_QUOTES, 'UTF-8') . '</a>';
    }

    if ($links === []) {
        return '';
    }

    return '<nav aria-label="Documentation pagination"><p>' . implode(' | ', $links) . '</p></nav>';
}

function microphp_docs_render_page(string $slug): void
{
    $pages = microphp_docs_pages();

    if (!isset($pages[$slug])) {
        http_response_code(404);
        $page = [
            'title' => 'Documentation Page Not Found',
            'description' => 'The requested documentation page does not exist.',
            'content' => '<section><h1>Documentation Page Not Found</h1><p>The requested documentation page does not exist. Choose a topic from the documentation navigation.</p></section>',
        ];
        $currentSlug = '';
    } else {
        $page = $pages[$slug];
        $currentSlug = $slug;
    }

    echo microphp_docs_navigation($currentSlug);
    echo '<article>';
    echo $page['content'];
    echo '</article>';
    echo microphp_docs_pagination($currentSlug);
}
