<?php

declare(strict_types=1);

namespace MicroPHP;

use RuntimeException;
use Throwable;

final class LayoutRenderer
{
    /** @param array<string,mixed> $variables */
    public function render(string $file, array $variables): string
    {
        $real = realpath($file);
        $root = realpath(defined('LAYOUTS_PATH') ? LAYOUTS_PATH : ROOT_PATH . '/app/layouts');
        if ($real === false || $root === false || !is_file($real)
            || ($real !== $root && !str_starts_with($real . DIRECTORY_SEPARATOR, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Layout is outside the configured layout root.');
        }
        extract($variables, EXTR_SKIP);
        ob_start();
        try { require $real; }
        catch (Throwable $e) { ob_end_clean(); throw $e; }
        return ob_get_clean();
    }
}
