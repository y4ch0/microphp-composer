#!/usr/bin/env php
<?php
/**
 * CLI tool for managing compiled MicroPHP view cache.
 *
 * Usage:
 *   php bin/view-cache.php warm
 *   php bin/view-cache.php clear
 *   php bin/view-cache.php stats
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require_once dirname(__DIR__) . '/bootstrap/app.php';

use MicroPHP\View;

$command = $argv[1] ?? null;

switch ($command) {
    case 'warm':
        $start = microtime(true);
        $result = View::warmCache();
        $elapsed = round((microtime(true) - $start) * 1000);

        echo "Compiled views: {$result['compiled']} ({$elapsed} ms)\n";

        if (!empty($result['errors'])) {
            echo "\nCompilation errors:\n";
            foreach ($result['errors'] as $file => $message) {
                echo "  - {$file}: {$message}\n";
            }
            exit(1);
        }
        exit(0);

    case 'clear':
        $deleted = View::clearCache();
        echo "Deleted cache files: {$deleted}\n";
        exit(0);

    case 'stats':
        $stats = View::cacheStats();
        echo "Cache files: {$stats['files']}\n";
        echo "Total size:  " . number_format($stats['bytes'] / 1024, 2) . " KB\n";
        if ($stats['oldest'] !== null) {
            echo "Oldest file: " . date('Y-m-d H:i:s', $stats['oldest']) . "\n";
            echo "Newest file: " . date('Y-m-d H:i:s', $stats['newest']) . "\n";
        }
        exit(0);

    default:
        echo "Usage: php bin/view-cache.php [warm|clear|stats]\n";
        exit(1);
}
