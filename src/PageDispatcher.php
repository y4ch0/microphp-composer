<?php

declare(strict_types=1);

namespace MicroPHP;

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

/** Canonical frontend dispatcher; Router remains a compatibility facade. */
final class PageDispatcher
{
    public function __construct(private readonly Router $router) {}

    public function dispatch(?Request $request = null): Response
    {
        return $this->router->dispatchCanonical($request);
    }
}
