<?php

return auth_middleware([
    [
        'session_key' => 'user.id',
        'check' => '/^\d+$/',
        'on_fail' => 'Youre unauthorized to view this page',
    ],
    [
        'session_key' => 'user.role',
        'check' => ['Admin', 'Editor'],
        'on_fail' => 'Youre unauthorized to view this page',
    ],
]);
