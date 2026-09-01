<?php
// /App/Pages/admin/_guard.php

return auth_access([
    [
        'session_key' => 'user.id', // Check that a user is logged in
        'check'       => '/^\d+$/', // User ID must be a number (regex check)
        'on_fail'     => 'Youre unauthorized to view this page',
    ],
    [
        'session_key' => 'user.role',
        'check'       => ['Admin', "Editor"], // Role must be exactly 'Admin'
        'on_fail'     => 'Youre unauthorized to view this page',
    ]
]);