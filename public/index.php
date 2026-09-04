<?php

declare(strict_types=1);

use MicroPHP\Http\Request;

error_reporting(E_ALL);
// Fail closed until application configuration has been loaded successfully.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');

$app->handle(Request::capture())->send();
