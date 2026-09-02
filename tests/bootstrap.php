<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/bootstrap/app.php';

function test_temp_dir(string $prefix): string
{
    $dir = sys_get_temp_dir() . '/microphp-' . $prefix . '-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    return $dir;
}

function test_remove_tree(string $path): void
{
    if (!is_dir($path)) { return; }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function render_compiled(string $template, array $data = []): string
{
    extract($data, EXTR_SKIP);
    $__view_instance = new MicroPHP\View();
    ob_start();
    try { eval('?>' . MicroPHP\View::compile($template)); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
    return ob_get_clean();
}
