<?php

declare(strict_types=1);

namespace MicroPHP\Routing;

final class RouteMatch
{
    /**
     * @param array<string,string> $params
     * @param string[] $segments Filesystem segment names that were matched.
     */
    public function __construct(
        public readonly string $directory,
        public readonly array $params = [],
        public readonly array $segments = [],
    ) {
    }
}
