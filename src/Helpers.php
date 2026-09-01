<?php
/**
 * MicroPHP Framework
 * Global helper functions.
 */

use MicroPHP\Http\MiddlewareInterface;
use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

/**
 * Gets a named parameter from the URL.
 *
 * @param string $name The name of the parameter (e.g., 'userId').
 * @param mixed $default A default value to return if the parameter doesn't exist.
 * @return mixed
 */
function route_param($name, $default = null, ?Request $request = null) {
    if ($request === null) {
        return $default;
    }

    $value = $request->route((string) $name, $default);

    return is_scalar($value) ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') : $value;
}

/**
 * Gets the current request path from the URL (e.g., '/admin/users').
 *
 * @return string The current path.
 */
function current_path() {
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

/**
 * Gets the full current URL including the domain and protocol.
 *
 * @return string The full current URL.
 */
function current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . "://" . $host . $_SERVER['REQUEST_URI'];
}

/**
 * Creates a user-friendly message and stores it in the session.
 *
 * @param string $type The type of message (e.g., 'success', 'error').
 * @param string $message The message text.
 * @return void
 */
function set_message($type, $message) {
    $_SESSION['messages'][] = ['type' => $type, 'text' => $message];
}

/**
 * Displays all stored messages.
 *
 * @return void
 */
function display_messages() {
    if (isset($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $msg) {
            $color = $msg['type'] === 'success' ? 'green' : 'red';
            echo "<div style='padding: 10px; border: 1px solid {$color}; color: {$color}; margin-bottom: 10px;'>";
            echo htmlspecialchars($msg['text']);
            echo "</div>";
        }
        unset($_SESSION['messages']);
    }
}

/**
 * Redirects the user to a different page.
 *
 * @param string $url The URL to redirect to.
 * @param int $statusCode The HTTP status code for the redirect (default is 302).
 * @return never
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * Return the first failed access rule message/action, or null when all rules pass.
 *
 * @param array $rules An array of rules to validate against the user's session.
 * @return string|null
 */
function microphp_access_failure(array $rules): ?string {
    // Helper to safely get a nested value from an array using dot notation.
    $get_nested_value = function($array, $key) {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!is_array($array) || !array_key_exists($k, $array)) {
                return null;
            }
            $array = $array[$k];
        }
        return $array;
    };

    foreach ($rules as $rule) {
        $session_key = $rule['session_key'] ?? null;
        $check_value = $rule['check'] ?? null;
        $on_fail_action = $rule['on_fail'] ?? 'You do not have permission to access this page.';

        if (!$session_key) {
            continue;
        }

        $session_value = $get_nested_value($_SESSION ?? [], $session_key);

        $is_valid = false;
        if (is_array($check_value)) {
            $is_valid = in_array($session_value, $check_value);
        } elseif (is_string($check_value) && preg_match('/^\/.*\/[a-zA-Z]*$/', $check_value)) {
            $is_valid = is_scalar($session_value) && preg_match($check_value, (string) $session_value);
        } else {
            $is_valid = ($session_value === $check_value);
        }

        if (!$is_valid) {
            return (string) $on_fail_action;
        }
    }

    return null;
}

/**
 * Creates a configurable guard handler for use in _guard.php files.
 *
 * @param array $rules An array of rules to validate against the user's session.
 * @param bool $override If true, this guard will ignore all parent guards.
 * @return array The configuration array expected by the Router.
 */
function auth_access(array $rules, bool $override = false) {
    $handler = function($router, $params) use ($rules) {
        $on_fail_action = microphp_access_failure($rules);

        if ($on_fail_action !== null) {
            if (str_starts_with($on_fail_action, '/')) {
                return Response::redirect($on_fail_action);
            } else {
                return $router->forbiddenResponse($on_fail_action);
            }
        }

        return true;
    };

    return [
        'handler' => $handler,
        'override' => $override,
    ];
}

/**
 * Creates a page middleware configuration for use in _middleware.php files.
 *
 * @param mixed $middleware One middleware or a list of middleware.
 * @param bool $override If true, this middleware file will ignore all parent middleware files.
 * @return array{middleware: array<int,MiddlewareInterface|callable>, override: bool}
 */
function page_middleware(mixed $middleware, bool $override = false): array {
    $items = $middleware instanceof MiddlewareInterface || is_callable($middleware) || is_string($middleware)
        ? [$middleware]
        : $middleware;

    return [
        'middleware' => $items,
        'override' => $override,
    ];
}

/**
 * Creates an API middleware configuration for app/api/_middleware.php
 * or app/api/<version>/_middleware.php files.
 *
 * @param mixed $middleware One middleware or a list of middleware.
 * @param bool $override If true, version middleware ignores parent API middleware files.
 * @return array{middleware: array<int,MiddlewareInterface|callable>, override: bool}
 */
function api_middleware(mixed $middleware, bool $override = false): array {
    return page_middleware($middleware, $override);
}

/**
 * Creates session-rule middleware equivalent to auth_access(), for _middleware.php files.
 *
 * @param array $rules An array of rules to validate against the user's session.
 * @param bool $override If true, this middleware file will ignore all parent middleware files.
 * @return array{middleware: array<int,callable>, override: bool}
 */
function auth_middleware(array $rules, bool $override = false): array {
    return page_middleware(
        function (Request $request, callable $next) use ($rules): Response {
            $on_fail_action = microphp_access_failure($rules);

            if ($on_fail_action !== null) {
                if (str_starts_with($on_fail_action, '/')) {
                    return Response::redirect($on_fail_action);
                }

                return Response::html(
                    '<h1>403 Forbidden</h1><p>' . htmlspecialchars($on_fail_action, ENT_QUOTES, 'UTF-8') . '</p>',
                    403
                );
            }

            return $next($request);
        },
        $override
    );
}
