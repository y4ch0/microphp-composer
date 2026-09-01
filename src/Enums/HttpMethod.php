<?php
/**
 * MicroPHP Framework
 * Backed enum for HTTP methods — for IDE-checked comparisons instead of
 * bare strings (e.g. in Api route definitions or middleware).
 */

namespace MicroPHP\Enums;

enum HttpMethod: string
{
    case Get = 'GET';
    case Head = 'HEAD';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
    case Options = 'OPTIONS';
}
