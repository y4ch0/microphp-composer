<?php

declare(strict_types=1);

namespace MicroPHP;

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

/** Canonical API request dispatcher; Api remains the legacy registration facade. */
final class ApiDispatcher
{
    public function __construct(private readonly Api $api) {}

    public function dispatch(?Request $request = null): Response
    {
        return $this->api->dispatchCanonical($request ?? Request::fromGlobals());
    }
}
