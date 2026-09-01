<?php

declare(strict_types=1);

namespace MicroPHP\Routing;

final readonly class RouteMatch
{
    /**
     * @param array<string,string> $params
     * @param string[] $segments Filesystem segment names that were matched.
     */
    public function __construct(
        public string $directory,
        public array $params = [],
        public array $segments = [],
    ) {
    }
}
