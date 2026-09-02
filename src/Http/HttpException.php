<?php

declare(strict_types=1);

namespace MicroPHP\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $safeMessage,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, 0, $previous);
    }
}
