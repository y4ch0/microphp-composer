#!/usr/bin/env php
<?php
/**
 * Interactive setup for projects created from the MicroPHP skeleton.
 *
 * Usage:
 *   php bin/setup-project.php
 *   php bin/setup-project.php --force
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

const MICROPHP_SUPPORTED_DB_DRIVERS = ['sqlite', 'mysql', 'mariadb', 'pgsql', 'sqlsrv', 'mongodb'];
const MICROPHP_SETUP_VERSION = '1.1.2';
const MICROPHP_EMPTY_VALUE_TOKEN = '-';

$rootPath = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$force = in_array('--force', $arguments, true);

$envPath = $rootPath . '/.env';

try {
    printSetupBanner(microphpCoreVersion($rootPath), MICROPHP_SETUP_VERSION);
    requireInteractiveSetup($arguments);
    ensureRuntimeDirectories($rootPath);

    if (is_file($envPath) && !$force) {
        echo ".env already exists. Run composer run microphp:setup -- --force to rebuild it.\n";
        exit(0);
    }

    $values = promptForEnvironment(defaultEnvironment($rootPath));

    writeEnvironmentFile($envPath, $values);
    ensureSqliteDatabase($rootPath, $values);

    echo "MicroPHP project setup complete.\n";
    echo "Environment: {$envPath}\n";
    echo "Run locally: php -S localhost:8000 -t public public/index.php\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "MicroPHP setup failed: {$exception->getMessage()}\n");
    exit(1);
}

function printSetupBanner(string $coreVersion, string $setupVersion): void
{
    echo <<<'TEXT'
 __  __ _                 ____  _   _ ____        __     __
|  \/  (_) ___ _ __ ___  |  _ \| | | |  _ \      /  \~~~/  \
| |\/| | |/ __| '__/ _ \ | |_) | |_| | |_) |    (    ..     )
| |  | | | (__| | | (_) ||  __/|  _  |  __/      \  __  __/
|_|  |_|_|\___|_|  \___/ |_|   |_| |_|_|          `-|__|-'

TEXT;

    echo "MicroPHP core: {$coreVersion}\n";
    echo "Setup script:  {$setupVersion}\n";
    echo "Beginning setup...\n\n";
}

function microphpCoreVersion(string $rootPath): string
{
    $autoloadPath = $rootPath . '/vendor/autoload.php';
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (class_exists('\\Composer\\InstalledVersions')) {
        try {
            $rootPackage = \Composer\InstalledVersions::getRootPackage();
            $version = $rootPackage['pretty_version'] ?? $rootPackage['version'] ?? null;
            if (is_string($version) && $version !== '' && !str_contains($version, 'no-version-set')) {
                return $version;
            }
        } catch (Throwable) {
        }
    }

    $composerJson = $rootPath . '/composer.json';
    if (is_file($composerJson)) {
        $package = json_decode((string) file_get_contents($composerJson), true);
        $version = is_array($package) ? ($package['version'] ?? null) : null;
        if (is_string($version) && $version !== '') {
            return $version;
        }
    }

    return 'dev-main';
}

/**
 * @param string[] $arguments
 */
function requireInteractiveSetup(array $arguments): void
{
    $unknownArguments = array_values(array_diff($arguments, ['--force']));
    if ($unknownArguments !== []) {
        throw new RuntimeException(
            'Unsupported setup option(s): ' . implode(', ', $unknownArguments) . '. Supported option: --force.'
        );
    }

    if (envFlagEnabled('COMPOSER_NO_INTERACTION')) {
        throw new RuntimeException(
            'Composer is running in non-interactive mode. Run setup from an interactive terminal without --no-interaction.'
        );
    }

    if (envFlagEnabled('CI')) {
        throw new RuntimeException(
            'CI mode is enabled, but MicroPHP setup requires an interactive terminal.'
        );
    }

}

/**
 * @return array<string,string>
 */
function defaultEnvironment(string $rootPath): array
{
    $projectName = basename($rootPath);
    $projectName = trim((string) preg_replace('/[-_]+/', ' ', $projectName));
    $projectName = ucwords($projectName ?: 'MicroPHP Application');

    return [
        'APP_ENV' => 'local',
        'APP_DEBUG' => 'true',
        'PROJECT_NAME' => $projectName,
        'API_SERVICE_ENABLED' => 'true',
        'PAGE_ACCESS_MODE' => 'both',
        'DB_DRIVER' => 'sqlite',
        'DB_DSN' => '',
        'DB_PATH' => 'database/library.db',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '',
        'DB_NAME' => 'microphp',
        'DB_USER' => '',
        'DB_PASS' => '',
        'DB_AUTH_SOURCE' => '',
        'VIEW_CACHE_TRUST' => 'false',
    ];
}

/**
 * @param array<string,string> $defaults
 * @return array<string,string>
 */
function promptForEnvironment(array $defaults): array
{
    echo "MicroPHP project setup\n";
    echo "Every prompt requires an explicit answer.\n";
    echo "Suggested values are shown in brackets. Type the value you want to use.\n";
    echo "For optional empty values, type " . MICROPHP_EMPTY_VALUE_TOKEN . ".\n\n";

    $values = $defaults;
    $values['PROJECT_NAME'] = promptRequired('Project display name', $defaults['PROJECT_NAME']);
    $values['APP_ENV'] = promptRequired('Environment name', $defaults['APP_ENV']);
    $values['APP_DEBUG'] = promptBool('Enable debug output', true) ? 'true' : 'false';
    $values['API_SERVICE_ENABLED'] = promptBool('Enable /api routes', true) ? 'true' : 'false';
    $values['PAGE_ACCESS_MODE'] = promptChoice('Page access mode', ['both', 'middleware', 'guard'], $defaults['PAGE_ACCESS_MODE']);
    $values['VIEW_CACHE_TRUST'] = promptBool('Trust warmed view cache files', false) ? 'true' : 'false';
    $values['DB_DRIVER'] = promptChoice('Database driver', MICROPHP_SUPPORTED_DB_DRIVERS, $defaults['DB_DRIVER']);

    if ($values['DB_DRIVER'] === 'sqlite') {
        $values['DB_PATH'] = promptRequired('SQLite database path', $defaults['DB_PATH']);
        $values['DB_DSN'] = '';
        return $values;
    }

    if ($values['DB_DRIVER'] === 'mongodb') {
        $values['DB_HOST'] = promptRequired('MongoDB host or URI', $defaults['DB_HOST']);
        $values['DB_PORT'] = promptRequired('MongoDB port', '27017');
        $values['DB_NAME'] = promptRequired('MongoDB database name', $defaults['DB_NAME']);
        $values['DB_USER'] = promptOptional('MongoDB username', $defaults['DB_USER']);
        $values['DB_PASS'] = promptSecret('MongoDB password', $defaults['DB_PASS']);
        $values['DB_AUTH_SOURCE'] = promptOptional('MongoDB auth source', $defaults['DB_AUTH_SOURCE']);
        return $values;
    }

    $defaultPort = match ($values['DB_DRIVER']) {
        'pgsql' => '5432',
        'sqlsrv' => '1433',
        default => '3306',
    };

    $values['DB_HOST'] = promptRequired('Database host', $defaults['DB_HOST']);
    $values['DB_PORT'] = promptRequired('Database port', $defaultPort);
    $values['DB_NAME'] = promptRequired('Database name', $defaults['DB_NAME']);
    $values['DB_USER'] = promptOptional('Database username', $defaults['DB_USER']);
    $values['DB_PASS'] = promptSecret('Database password', $defaults['DB_PASS']);
    $values['DB_DSN'] = promptOptional('Explicit DSN override', $defaults['DB_DSN']);

    return $values;
}

function promptRequired(string $label, string $suggestion = ''): string
{
    while (true) {
        $answer = readPromptAnswer($label, $suggestion);
        if ($answer !== '') {
            return $answer;
        }

        echo "This value is required. Type the value you want to use.\n";
    }
}

function promptOptional(string $label, string $suggestion = ''): string
{
    while (true) {
        $answer = readPromptAnswer($label, $suggestion);
        if ($answer === MICROPHP_EMPTY_VALUE_TOKEN) {
            return '';
        }
        if ($answer !== '') {
            return $answer;
        }

        echo "Type a value, or type " . MICROPHP_EMPTY_VALUE_TOKEN . " to leave this empty.\n";
    }
}

function promptSecret(string $label, string $suggestion = ''): string
{
    return promptOptional($label, $suggestion);
}

function readPromptAnswer(string $label, string $suggestion = ''): string
{
    $suffix = $suggestion === '' ? '' : " [{$suggestion}]";
    fwrite(STDOUT, "{$label}{$suffix}: ");
    $answer = readInputLine();

    if ($answer === false) {
        throw new RuntimeException(
            "Input closed while waiting for: {$label}. Run setup from a terminal that accepts keyboard input."
        );
    }

    return trim($answer);
}

function readInputLine(): string|false
{
    static $consoleInput = null;

    if (is_resource($consoleInput)) {
        return fgets($consoleInput);
    }

    $answer = fgets(STDIN);
    if ($answer !== false) {
        return $answer;
    }

    $consoleInput = openConsoleInputStream();
    if (is_resource($consoleInput)) {
        return fgets($consoleInput);
    }

    return false;
}

/**
 * @return resource|null
 */
function openConsoleInputStream()
{
    $streams = DIRECTORY_SEPARATOR === '\\'
        ? ['CONIN$', 'CON']
        : ['/dev/tty'];

    foreach ($streams as $stream) {
        $handle = @fopen($stream, 'r');
        if (is_resource($handle)) {
            return $handle;
        }
    }

    return null;
}

function envFlagEnabled(string $name): bool
{
    $value = getenv($name);
    if ($value === false) {
        return false;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

/**
 * @param string[] $choices
 */
function promptChoice(string $label, array $choices, string $default): string
{
    $normalizedChoices = [];
    foreach ($choices as $choice) {
        $normalizedChoices[strtolower($choice)] = $choice;
    }

    while (true) {
        $answer = strtolower(readPromptAnswer($label . ' (' . implode('/', $choices) . ')', $default));
        if (isset($normalizedChoices[$answer])) {
            return $normalizedChoices[$answer];
        }

        echo "Choose one of: " . implode(', ', $choices) . "\n";
    }
}

function promptBool(string $label, bool $default): bool
{
    $defaultText = $default ? 'yes' : 'no';

    while (true) {
        $answer = strtolower(readPromptAnswer($label . ' (yes/no)', $defaultText));
        if (in_array($answer, ['yes', 'y', 'true', '1'], true)) {
            return true;
        }
        if (in_array($answer, ['no', 'n', 'false', '0'], true)) {
            return false;
        }

        echo "Answer yes or no.\n";
    }
}

/**
 * @param array<string,string> $values
 */
function writeEnvironmentFile(string $envPath, array $values): void
{
    $lines = [
        'APP_ENV=' . envValue($values['APP_ENV']),
        'APP_DEBUG=' . envValue($values['APP_DEBUG']),
        'PROJECT_NAME=' . envValue($values['PROJECT_NAME']),
        'API_SERVICE_ENABLED=' . envValue($values['API_SERVICE_ENABLED']),
        'PAGE_ACCESS_MODE=' . envValue($values['PAGE_ACCESS_MODE']),
        '',
        'DB_DRIVER=' . envValue($values['DB_DRIVER']),
        'DB_DSN=' . envValue($values['DB_DSN']),
        'DB_PATH=' . envValue($values['DB_PATH']),
        'DB_HOST=' . envValue($values['DB_HOST']),
        'DB_PORT=' . envValue($values['DB_PORT']),
        'DB_NAME=' . envValue($values['DB_NAME']),
        'DB_USER=' . envValue($values['DB_USER']),
        'DB_PASS=' . envValue($values['DB_PASS']),
        'DB_AUTH_SOURCE=' . envValue($values['DB_AUTH_SOURCE']),
        '',
        'VIEW_CACHE_TRUST=' . envValue($values['VIEW_CACHE_TRUST']),
        '',
    ];

    if (file_put_contents($envPath, implode(PHP_EOL, $lines)) === false) {
        throw new RuntimeException("Unable to write {$envPath}");
    }
}

function envValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9_\\.\\-\\/\\\\:]+$/', $value)) {
        return $value;
    }

    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function ensureRuntimeDirectories(string $rootPath): void
{
    foreach ([
        'database',
        'public/assets',
        'var/cache/views',
        'var/log',
        'var/sessions',
    ] as $relativePath) {
        $path = $rootPath . '/' . $relativePath;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException("Unable to create {$path}");
        }
    }
}

/**
 * @param array<string,string> $values
 */
function ensureSqliteDatabase(string $rootPath, array $values): void
{
    if ($values['DB_DRIVER'] !== 'sqlite' || $values['DB_PATH'] === '') {
        return;
    }

    $path = normalizeProjectPath($rootPath, $values['DB_PATH']);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create {$directory}");
    }

    if (!is_file($path) && file_put_contents($path, '') === false) {
        throw new RuntimeException("Unable to create {$path}");
    }
}

function normalizeProjectPath(string $rootPath, string $path): string
{
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    return $rootPath . '/' . ltrim($path, '/\\');
}
