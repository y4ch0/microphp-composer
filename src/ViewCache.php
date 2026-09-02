<?php
namespace MicroPHP;

/**
 * ViewCache
 *
 * Manages the lifecycle of compiled .micro.php templates, separately from the
 * directive-compilation logic (which is still performed by View::compileString()).
 */
class ViewCache
{
    private string $cachePath;
    private bool $trustCache;

    /** Permissions for a newly created cache directory — no write access for "others". */
    private const DIR_PERMISSIONS = 0755;

    /**
     * Create a cache manager for compiled templates.
     *
     * @param string $cachePath Absolute path to the cache directory.
     * @param bool $trustCache Whether existing cache files should be trusted without freshness checks.
     */
    public function __construct(string $cachePath, bool $trustCache = false)
    {
        $this->cachePath = rtrim($cachePath, '/\\');
        $this->trustCache = $trustCache;
        $this->ensureCacheDirExists();
    }

    /**
     * Returns the path to the current, include()-ready cache file for the given
     * source — compiling (and atomically storing) it anew only when necessary.
     *
     * @param string   $sourceFile Absolute path to the .micro.php source file
     * @param callable $compiler   function(string $sourceCode): string
     * @return string Absolute path to the compiled cache file.
     */
    public function resolve(string $sourceFile, callable $compiler): string
    {
        if (!is_file($sourceFile)) {
            throw new \RuntimeException("View does not exist: {$sourceFile}");
        }

        $cacheFile = $this->cacheFileFor($sourceFile);

        if ($this->trustCache) {
            // Production: trust whatever is on disk. Zero stat() calls on the source.
            // A missing file means deployment forgot to run warmAll()
            // — we compile as a fallback, but this is a signal to fix the deployment pipeline.
            if (file_exists($cacheFile)) {
                return $cacheFile;
            }
        } elseif ($this->isFresh($sourceFile, $cacheFile)) {
            return $cacheFile;
        }

        $this->compileAndStore($sourceFile, $cacheFile, $compiler);

        return $this->confine($cacheFile);
    }

    /**
     * Warms the cache for the given list of source files — compiles each one
     * unconditionally (regardless of current freshness) and stores it.
     * Intended to be run once, at deployment time (see bin/view-cache.php).
     *
     * @param iterable<string> $sourceFiles
     * @param callable $compiler function(string $sourceCode): string
     * @return array{compiled: int, errors: array<string,string>}
     */
    public function warmAll(iterable $sourceFiles, callable $compiler): array
    {
        $compiled = 0;
        $errors = [];

        foreach ($sourceFiles as $sourceFile) {
            try {
                $cacheFile = $this->cacheFileFor($sourceFile);
                $this->compileAndStore($sourceFile, $cacheFile, $compiler);
                $compiled++;
            } catch (\Throwable $e) {
                $errors[$sourceFile] = $e->getMessage();
            }
        }

        return ['compiled' => $compiled, 'errors' => $errors];
    }

    /**
     * Deletes all cache files. Returns the number of files deleted.
     * To be invoked explicitly (CLI / deployment step), never implicitly while
     * handling a user request.
     *
     * @return int Number of deleted cache files.
     */
    public function clearAll(): int
    {
        $deleted = 0;
        foreach (glob($this->cachePath . '/*.php') ?: [] as $file) {
            if (is_file($file) && $this->isInsideCacheDir($file) && @unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Basic cache statistics — useful for monitoring/debugging.
     *
     * @return array{files: int, bytes: int, oldest: ?int, newest: ?int}
     */
    public function stats(): array
    {
        $files = glob($this->cachePath . '/*.php') ?: [];
        $bytes = 0;
        $oldest = null;
        $newest = null;

        foreach ($files as $file) {
            $bytes += filesize($file) ?: 0;
            $mtime = filemtime($file) ?: null;
            if ($mtime !== null) {
                $oldest = $oldest === null ? $mtime : min($oldest, $mtime);
                $newest = $newest === null ? $mtime : max($newest, $mtime);
            }
        }

        return [
            'files'  => count($files),
            'bytes'  => $bytes,
            'oldest' => $oldest,
            'newest' => $newest,
        ];
    }

    // --- Internal ---

    private function cacheFileFor(string $sourceFile): string
    {
        // Stable key: the source path. NOT mtime — see point 1 in the class-level comment.
        //
        // IMPORTANT: we normalize via realpath() before hashing. The same file can
        // be described by different, but equivalent, strings depending on which
        // part of the code built the path. Without normalization, two different
        // paths pointing to the same file would get two different hashes, creating
        // two separate cache files for a single template. realpath() guarantees a
        // canonical representation regardless of how the path was constructed.
        $canonical = realpath($sourceFile) ?: $sourceFile;
        $hash = md5($canonical);
        return $this->cachePath . '/' . $hash . '.php';
    }

    private function isFresh(string $sourceFile, string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }
        // Single stat() comparison — no directory scanning.
        return filemtime($cacheFile) >= filemtime($sourceFile);
    }

    private function compileAndStore(string $sourceFile, string $cacheFile, callable $compiler): void
    {
        $source = file_get_contents($sourceFile);
        if ($source === false) {
            throw new \RuntimeException("Unable to read view: {$sourceFile}");
        }

        $compiledBody = $compiler($source);

        $guard = '<?php if (!defined(\'MICROPHP_VIEW_CONTEXT\')) { '
               . 'http_response_code(403); exit(\'Direct access is not permitted.\'); } ?>';
        $header = $guard . PHP_EOL . '<?php // compiled from: ' . $sourceFile . ' ?>' . PHP_EOL;

        $compiled = $header . $compiledBody;

        // Atomic write: tmp file in the SAME directory (required for rename() atomicity
        // on the same filesystem) + LOCK_EX while writing to the temporary file.
        $tmpFile = $cacheFile . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($tmpFile, $compiled, LOCK_EX) === false) {
            @unlink($tmpFile);
            throw new \RuntimeException("Unable to write temporary cache file: {$tmpFile}");
        }

        if (!@rename($tmpFile, $cacheFile)) {
            @unlink($tmpFile);
            throw new \RuntimeException("Unable to replace cache file: {$cacheFile}");
        }
    }

    private function ensureCacheDirExists(): void
    {
        if (is_dir($this->cachePath)) {
            return;
        }
        if (!@mkdir($this->cachePath, self::DIR_PERMISSIONS, true) && !is_dir($this->cachePath)) {
            throw new \RuntimeException("Unable to create cache directory: {$this->cachePath}");
        }
    }

    private function isInsideCacheDir(string $file): bool
    {
        $real = realpath($file);
        $base = realpath($this->cachePath);
        if ($real === false || $base === false) {
            return false;
        }

        $real = rtrim($real, DIRECTORY_SEPARATOR);
        $base = rtrim($base, DIRECTORY_SEPARATOR);

        return $real === $base
            || str_starts_with($real . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR);
    }

    private function confine(string $cacheFile): string
    {
        if (!$this->isInsideCacheDir($cacheFile)) {
            throw new \RuntimeException("Invalid cache file path: {$cacheFile}");
        }
        return $cacheFile;
    }
}
