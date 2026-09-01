<?php

return auth_middleware([
    [
        'session_key' => 'user.role',
        'check' => ['Admin'],
        'on_fail' => 'This area is restricted to administrators and editors. You can edit this text and replace it with redirect like /login to redirect user to other page.',
    ],
]);
