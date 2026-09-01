<?php

declare(strict_types=1);

use MicroPHP\Http\Request;
use MicroPHP\Http\Response;

return function (Request $request): Response {
    return Response::json([
        'status' => 'ok',
        'version' => 'v1',
    ]);
};
