<?php

declare(strict_types=1);

use MicroPHP\Http\Request;

error_reporting(E_ALL);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');

$app->handle(Request::capture())->send();
