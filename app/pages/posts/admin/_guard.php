<?php
// /App/Pages/dashboard/_guard.php

return auth_access([
    [
        'session_key' => 'user.role',
        'check'       => ['Admin'], // Role can be one of these (in_array check)
        'on_fail'     => 'This area is restricted to administrators and editors. You can edit this text and replace it with redirect like /login to redirect user to other page.',
    ]
]);